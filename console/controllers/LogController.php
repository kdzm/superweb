<?php
/**
 * Created by PhpStorm.
 * User: lychee
 * Date: 2018/6/20
 * Time: 15:58
 */

namespace console\controllers;

use backend\models\LogOttActivity;
use backend\models\LogOttGenre;
use backend\models\LogOttTmp;
use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\redis\Connection;
use backend\models\LogInterface;
use backend\models\LogStatics;
use backend\models\LogTmp;
use backend\models\ProgramLog;
use common\components\Func;
use common\models\MainClass;
/**
 * 日志分析控制器
 * Class LogController
 * @package console\controllers
 */
class LogController extends Controller
{
    public $db = 14;
    /**
     * @var Connection
     */
    public $redis;

    public $interfaces = ['watch', 'total', 'notify', 'getCountryList', 'getOttRecommend', 'getMajorEvent', 'register', 'ottCharge', 'getNewApp', 'renew', 'getAppMarket', 'getIptvList', 'getOttNewList', 'getClientToken']; //vods

    public function actionHelp()
    {
        $this->stdout("php yii log/analyse : 实时统计接口日志数据" . PHP_EOL, Console::FG_YELLOW);
        $this->stdout("php yii log/daily-log : 统计每日接口日志数据" . PHP_EOL, Console::FG_YELLOW);
        $this->stdout("php yii log/offline : 离线统计昨天接口日志数据" . PHP_EOL, Console::FG_YELLOW);
    }

    //日志实时统计（单独开进程运行）
    public function actionAnalyse()
    {
        //读取redis
        $this->redis = Yii::$app->redis;
        $this->redis->select($this->db);

        while (true) {
            //每次获取长度
            $length = $this->redis->llen('log');

            while ($length) {
                $this->deal();
                $length--;
            }

            sleep(15);
        }

    }


    /**
     * 离线统计活跃用户/接口使用情况
     * 调用方式： php yii log/offline
     * @param null $date
     */
    public function actionOffline($date = null)
    {
        if (!is_null($date) && preg_match('/\d{4}-\d{2}-\d{2}/', $date)) {
            $timestamp = strtotime($date);
        } else {
            $timestamp = strtotime('yesterday');
        }

        $this->beforeAnalyse($timestamp);
        $this->clearTmpTable();
        $this->actionImport($timestamp);
        $this->actionStatics($timestamp);
        $this->actionOttStatics($timestamp);
        $this->actionStaticProgram($timestamp);
        $this->actionStaticHour($timestamp);


        $this->stdout("任务执行结束");
    }

    protected function beforeAnalyse($timestamp)
    {
        // 防止统计重复重复执行log/offline 数据叠加
        $date = date('Y-m-d', $timestamp);
        ProgramLog::deleteAll(['date' => $date]);
        LogStatics::deleteAll(['date' => $date]);
        LogInterface::deleteAll(['date' => $date]);
    }

    /**
     * 统计节目播放情况
     * @param $timestamp
     */
    public function actionStaticProgram($timestamp)
    {
        $this->stdout("统计节目播放情况" . PHP_EOL);
        $filePaths = $this->getLogPaths($timestamp);

        $programs = [];

        foreach ($filePaths as $filePath) {
            if (file_exists($filePath) == false) continue;
            foreach (self::readLine($filePath) as $line) {
                //匹配header
                preg_match("/{.*}/", $line, $header);
                if ($header) {
                    $json = json_decode($header[0], true);
                    if (isset($json['header'])) {
                        $header = $json['header'];

                        if (in_array($header, ['tvnet','viettel','sohatv','thvl','hoabinhtv','v4live','migu','sohulive','hplus','newmigu','haoqu','tencent','vtv','ott','local'])) {

                            if (isset($json['name'])) {

                                if (isset($programs[$json['name']])) {
                                    $programs[$json['name']]++;
                                } else {
                                    $programs[$json['name']] = 1;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!empty($programs)) {

            $programLog = ProgramLog::findOne(['date' => date('Y-m-d', $timestamp)]);

            if (is_null($programLog)) {
                $programLog = new ProgramLog();
                $programLog->date = date('Y-m-d', $timestamp);
            } else {
                $server_programs = json_decode($programLog->server_program, true);
                foreach ($server_programs as $key => $val) {
                    if (array_key_exists($key, $programs)) {
                        $programs[$key] = $val + $programs[$key];
                    } else {
                        $programs[$key] = $val;
                    }
                }
            }

            arsort($programs);
            $programs = array_slice($programs,0,20);
            $programLog->server_program = $programLog->all_program = json_encode($programs);
            $programLog->save(false);
        }

    }

    // 按24小时统计接口调用
    public function actionStaticHour($timestamp)
    {
        $this->stdout("正在统计24小时接口调用情况" . PHP_EOL);
        $filePaths = $this->getLogPaths($timestamp);

        $data = [];

        // 遍历24小时
        for ($i=0; $i<=23; $i++) {
            $hour = sprintf('%02d', $i);

            $data['total'][$i] = 0;
            $data['getClientToken'][$i] = 0;
            $data['watch'][$i] = 0;
            $data['getOttNewList'][$i] = 0;
            $data['getIptvList'][$i] = 0;
            $data['getNewApp'][$i] = 0;

            foreach ($filePaths as $filePath) {
                if (file_exists($filePath) == false) continue;
                foreach (self::readLine($filePath) as $line) {

                    $result = preg_match("/\|\s?{$hour}(?=:)/", $line);

                    if ($result) {
                        $data['total'][$i]++;

                        //匹配header
                        preg_match("/{.*}/", $line, $header);
                        if ($header) {
                            $header = json_decode($header[0], true);
                            if (isset($header['header'])) {
                                $header = $header['header'];
                                if ($header == 'getClientToken') {
                                    $data['getClientToken'][$i]++;
                                } elseif ($header == 'getOttNewList') {
                                    $data['getOttNewList'][$i]++;
                                } elseif ($header == 'vods') {
                                    $data['getIptvList'][$i]++;
                                } elseif (in_array($header, ['getApp', 'getNewApp'])) {
                                    $data['getNewApp'][$i]++;
                                } elseif (in_array($header, ['tvnet','viettel','sohatv','thvl','hoabinhtv','v4live','migu','sohulive','hplus','newmigu','haoqu','tencent','vtv','ott','local'])) {
                                    $data['watch'][$i]++;
                                }
                            }
                        }
                    }
                }
            }

            //echo "{$hour}时请求数为{$data['total'][$i]}次" ,PHP_EOL;

        }

        $logInterface = LogInterface::findOne(['date' => date('Y-m-d', $timestamp)]);

        if (is_null($logInterface)) {
            $logInterface = new LogInterface();
            $logInterface->total = json_encode($data['total']);
            $logInterface->getOttNewList = json_encode($data['getOttNewList']);
            $logInterface->getIptvList = json_encode($data['getIptvList']);
            $logInterface->getNewApp = json_encode($data['getNewApp']);
            $logInterface->getClientToken = json_encode($data['getClientToken']);
            $logInterface->watch = json_encode($data['watch']);
            $logInterface->date = date('Y-m-d', $timestamp);
        } else {
            // 叠加处理
            $fields = ['total', 'getOttNewList', 'getIptvList', 'getNewApp', 'getClientToken', 'watch'];
            foreach ($fields as $field) {
                $logInterface->$field = json_encode(Func::array_value_sum($logInterface->$field, $data[$field]));
            }
        }

        $logInterface->save();

        $this->stdout("处理" . date('Y-m-d', $timestamp) . '成功' . PHP_EOL);
    }

    protected function clearTmpTable()
    {
        Yii::$app->db->createCommand("truncate " . LogTmp::tableName())->execute();
        Yii::$app->db->createCommand("truncate " . LogOttTmp::tableName())->execute();
    }

    // 从日志导入数据到mysql
    public function actionImport($timestamp)
    {
        $this->stdout("从日志导入数据到mysql" . PHP_EOL);
        $row = 0;
        $start = time();
        $filePaths = $this->getLogPaths($timestamp);
        $rows = [];

        foreach ($filePaths as $filePath) {
            if (file_exists($filePath) == false) continue;
            foreach (self::readLine($filePath) as $i => $line) {
                $row++;
                $this->dealJsonData($rows, $line, $timestamp);
            }
        }

        $this->theEndJsonData($rows);
        $end = time();
        $waste = $end - $start;
        $this->stdout("该文件有{$row}行数据,读取耗时{$waste}秒" . PHP_EOL);
    }


    protected function theEndJsonData($rows)
    {
        if ($rows) {
            Yii::$app->db->createCommand()->batchInsert(LogTmp::tableName(), ['header','mac','code'],$rows)->execute();
        }

    }

    // 记录某些分类的用户获取列表情况
    protected function staticsSomeOttGenre($line, $interface, $timestamp, $code)
    {
        if ($interface['header'] == 'getOttNewList' ) {
            $genre = $interface['data']['country']??($interface['data']['genre']??false);
            $logGenres = MainClass::getLogGenres();

            if (in_array($genre, $logGenres)) {

                // 判断方案号
                if (isset($interface['data']['scheme']) && strpos($interface['data']['scheme'], 'GALAXY') !== false) {
                    return false;
                }
                
                $date = date('Y-m-d', $timestamp);
                $time = Func::pregSieze('/(?<=\|)\s*(\d+:)+\d+/',$line);
                $dateTime = $date ." ". trim($time);

                $data = [
                    'date' => $date,
                    'timestamp' => strtotime($dateTime),
                    'mac' => $interface['uid'],
                    'genre' => $genre,
                    'code' => $code,
                    'scheme' => $interface['data']['scheme']
                ];

                Yii::$app->db->createCommand()->insert(LogOttActivity::tableName(),$data)->execute();

                $data = [
                    'mac' => $interface['uid'],
                    'genre' => $genre,
                    'code' => $code
                ];

                Yii::$app->db->createCommand()->insert(LogOttTmp::tableName(),$data)->execute();
            }
        }

        return true;
    }

    protected function staticsActivity(&$rows, $interface, $code)
    {
        $rows[] = [
            'header' => $interface['header'],
            'mac'  => $interface['uid'],
            'code' => $code
        ];

        if (count($rows) >= 800) {
            Yii::$app->db->createCommand()->batchInsert(LogTmp::tableName(), ['header','mac','code'],$rows)->execute();
            $rows = [];
        }
    }

    protected function getCode($line)
    {
        $code = Func::pregSieze('/(?<=ERROR:)\d+/', $line);
        return $code == false ? '0' : $code;
    }

    protected function dealJsonData(&$rows, $line, $timestamp)
    {
        $result = Func::pregSieze('/{.*}/', $line);

        if ($result) {
            $interface = json_decode($result, true);
            if (isset($interface['header']) && isset($interface['uid'])) {
                $code = $this->getCode($line);
                $this->staticsSomeOttGenre($line, $interface, $timestamp, $code);
                $this->staticsActivity($rows, $interface, $code);
            }
        }
    }

    // 利用 mysql 统计直播列表下载 情况
    public function actionOttStatics($timestamp)
    {
        // 查询当前有几个分类
        $genres = MainClass::find()->all();

        foreach ($genres as $genre) {

            $log = new LogOttGenre();
            if ($genre->list_name) {
                $log->date = date('Y-m-d', $timestamp);
                $log->genre = $genre->list_name;
                $log->download_time  = LogOttTmp::find()->where(['genre' => $genre->list_name])->count();
                $log->person_time = LogOttTmp::find()->where(['genre' => $genre->list_name])->groupBy('mac')->count();
                $log->same_version_time =  LogOttTmp::find()->where(['genre' => $genre->list_name,'code' => '16'])->count();

                if ($log->download_time) {
                    $log->save(false);
                }
            }
        }
    }

    // 利用mysql 统计接口数据
    public function actionStatics($timestamp)
    {
        $this->stdout("利用mysql 统计接口数据" . PHP_EOL);
        $data['active_user']        = LogTmp::find()->select('mac')->distinct()->count();
        $data['total']              = LogTmp::find()->count();
        $data['token']              = LogTmp::find()->where(['header' => 'getClientToken'])->count();
        $data['ott_list']           = LogTmp::find()->where(['header' => 'getOttNewList'])->count();
        $data['iptv_list']          = LogTmp::find()->where(['like', 'header', 'vods'])->count();
        $data['karaoke_list']       = LogTmp::find()->where(['header' => 'getKaraokeList'])->count();
        $data['epg']                = LogTmp::find()->where(['in','header',['getParadeList','getEPG']])->count();
        $data['app_upgrade']        = LogTmp::find()->where(['IN', 'header', ['getNewApp', 'getApp', 'upgrade']])->count();
        $data['firmware_upgrade']   = LogTmp::find()->where(['header' => 'getFirmware'])->count();
        $data['renew']              = LogTmp::find()->where(['header' => 'renew'])->count();
        $data['dvb_register']       = LogTmp::find()->where(['header' => 'register'])->count();
        $data['ott_charge']         = LogTmp::find()->where(['header' => 'ottCharge'])->count();
        $data['pay']                = LogTmp::find()->where(['header' => 'pay'])->count();
        $data['activateGenre']      = LogTmp::find()->where(['header' => 'activateGenre'])->count();
        $data['paypal_callback']    = LogTmp::find()->where(['header' => 'paypalCallback'])->count();
        $data['dokypay_callback']   = LogTmp::find()->where(['header' => 'return/dokypay'])->count();
        $data['getServerTime']      = LogTmp::find()->where(['header' => 'getServerTime'])->count();
        $data['play']               = LogTmp::find()->where(['in', 'header', [
            'local', 'sohatv', 'hplus', 'play'
        ]])->count();

        // 新增数据库数据
        // 查找昨天数据是否存在
        $model = LogStatics::findOne(['date' => date('Y-m-d', $timestamp)]);

        if (is_null($model)) {
            $model = new LogStatics();
            $model->date            = date('Y-m-d', $timestamp);
            $model->active_user     = $data['active_user'];
            $model->total           = $data['total'];
            $model->token           = $data['token'];
            $model->ott_list        = $data['ott_list'];
            $model->iptv_list       = $data['iptv_list'];
            $model->karaoke_list    = $data['karaoke_list'];
            $model->epg             = $data['epg'];
            $model->app_upgrade     = $data['app_upgrade'];
            $model->renew           = $data['renew'];
            $model->dvb_register    = $data['dvb_register'];
            $model->ott_charge      = $data['ott_charge'];
            $model->pay             = $data['pay'];
            $model->activateGenre   = $data['activateGenre'];
            $model->paypal_callback = $data['paypal_callback'];
            $model->dokypay_callback= $data['dokypay_callback'];
            $model->getServerTime   = $data['getServerTime'];
            $model->play            = $data['play'];
        } else {

            $model->active_user     += $data['active_user'];
            $model->total           += $data['total'];
            $model->token           += $data['token'];
            $model->ott_list        += $data['ott_list'];
            $model->iptv_list       += $data['iptv_list'];
            $model->karaoke_list    += $data['karaoke_list'];
            $model->epg             += $data['epg'];
            $model->app_upgrade     += $data['app_upgrade'];
            $model->renew           += $data['renew'];
            $model->dvb_register    += $data['dvb_register'];
            $model->ott_charge      += $data['ott_charge'];
            $model->pay             += $data['pay'];
            $model->activateGenre   += $data['activateGenre'];
            $model->paypal_callback += $data['paypal_callback'];
            $model->dokypay_callback+= $data['dokypay_callback'];
            $model->getServerTime   += $data['getServerTime'];
            $model->play            += $data['play'];
        }

        $model->save();
        $this->stdout("-----昨日数据分析----" . PHP_EOL);
        print_r($data);

    }

    protected function getLogPaths($timestamp)
    {
        return [
            '/var/www/log/ApiLog/' . date('Y', $timestamp) . '/' . date('m', $timestamp) . '/' . date('Ymd', $timestamp) . '.log',
            '/var/www/log/app/' . date('m', $timestamp) . '/' . date('Ymd', $timestamp) . '.log'
        ];
    }

    /**
     * 实时处理日志统计
     */
    private function deal()
    {
        $log = $this->redis->rpop('log');
        if ($log) {
            if (class_exists('SeasLog', false)) {
                \SeasLog::setLogger("ApiLog/" . date('Y/m'));
                \SeasLog::info($log);
            }
            $log   = explode('|', $log);
            // 接口时间
            $time  = isset($log[0]) ? $log[0] : false;
            // 接口ip
            $ip    = isset($log[1]) ? $log[1] : false;
            // 接口json字符串
            $json  = isset($log[2]) ? $log[2] : '';
            // 接口错误
            $error = isset($log[3])? $log[3] : false;

            $data = json_decode($json, true);

            $program = isset($data['class']) ? $data['class'] : false;
            $uid     = isset($data['uid']) ? $data['uid'] : false;
            $header  = isset($data['header']) ? $data['header'] : false;
            $name    = isset($data['name']) ? $data['name'] : false;
            $requestData = isset($data['data']) ? $data['data'] : false;

            if (empty($header)) return false;
            
            if ($program == false) {
                if (in_array($header, ['tvnet','viettel','sohatv','thvl','hoabinhtv','v4live','migu','sohulive','hplus','newmigu','haoqu','tencent','vtv','ott','local'])) {
                    $program = $header;
                    $header = false;
                }
            }

            // 按小时进行统计(全部)
            $key = "interface:" . date('m-d:') . 'hour';
            $this->hincyby($key, date('H'));
            $this->redis->expire($key, '96400');

            if (
                $header &&
                in_array($header, $this->interfaces)
            ) {

                // 全部接口按小时进行统计
                $key = "interface:" . date('m-d:H');
                $this->hincyby($key, $header);
                $this->redis->expire($key, '96400');

                // 接口按天数进行统计
                $key = "interface:" . date('m-d:') . "day";
                $this->hincyby($key, $header);
                $this->redis->expire($key, '96400');
            }

            // 按节目进行统计
            if ($program) {

                if ($name) {
                    $key = "program:" . date('m-d:') . 'day' ;
                    $this->hincyby($key, $name);
                    $this->redis->expire($key, '96400');
                }

                // 所有节目按小时统计
                $key = "program:" . date('m-d:') . 'hour';
                $this->hincyby($key, date('H'));
                $this->redis->expire($key, '96400');

                // 每个节目每天的观看次数
                $key = "program:" . date('m-d:') . 'set';
                $this->redis->hincrby($key, $name, 1);
                $this->redis->expire($key, '96400');
            }

        }
    }

    /**
     * 统计每天的接口调用情况
     */
    public function actionDailyLog()
    {
        $this->redis = Yii::$app->redis;
        $this->redis->select($this->db);
        $this->setTodayProgramData();

        $todayData = $this->getTodayInterfaceData();
        $this->setInterfaceLog($todayData);
    }

    private function setTodayProgramData()
    {
        $key = "program:" . date('m-d:') . 'day';
        $data = $this->redis->hgetall($key);

        $data = $this->_map($data);
        arsort($data);
        
        $data = array_slice($data, 0, 20);

        $log = ProgramLog::find()->where(['date' => date('Y-m-d')])->one();
        if ($log == false) {
            $log = new ProgramLog();
            $log->date = date('Y-m-d');
        }


        $log->all_program_sum = $log->server_program = json_encode($data);
        $log->save(false);
    }

    private function getTodayInterfaceData()
    {
        $today = [];
        $hours = $this->_generateHour();

        $all_key = "interface:" . date('m-d:') . 'hour';
        $all_data = $this->redis->hgetall($all_key);
        $all_data = $this->_map($all_data);

        $program_key = "program:" . date('m-d:') . 'hour';
        $program_data = $this->redis->hgetall($program_key);
        $program_data = $this->_map($program_data);

        foreach ($hours as $hour) {
            $key = "interface:" . date('m-d:') . $hour;
            $data = $this->redis->hgetall($key);
            $today[] = array_merge(
                ['total' => isset($all_data[$hour]) ? $all_data[$hour] : 0],
                ['watch' => isset($program_data[$hour]) ? $program_data[$hour] : 0],
                $this->_map($data))
            ;
        }

        return $today;
    }

    private function setInterfaceLog($todayData)
    {
        // 查询是否已经存在今天的数据 有就更新
        $log = LogInterface::find()->where(['date' => date('Y-m-d')])->one();
        if (is_null($log)) {
            $log = new LogInterface();
        }

        $interfaces = $this->interfaces;
        foreach ($interfaces as $interface) {
            $log->$interface = $this->_fill(ArrayHelper::getColumn($todayData, $interface));
        }

        $log->year = date('Y');
        $log->date = date('Y-m-d');

        $log->save(false);

    }

    private function _fill($data)
    {
        array_walk($data, function(&$v, $k) {
            if (empty($v)) {
                $v = 0;
            }
        });

        return json_encode($data);
    }

    private function _generateHour()
    {
        $hours = [];
        for ($h = 0; $h <= 23; $h++) {
            if ($h < 10) {
                $h = sprintf('%02d', $h);
            }
            $hours[] =  $h;
        }

        return $hours;
    }

    private function _map($data)
    {
        $map = [];
        if (!empty($data)) {
            $len = count($data);
            for ($i = 0; $i < $len; $i+=2) {
                if (isset($data[$i + 1]) && !empty($data[$i + 1])) {
                    $map[$data[$i]] = $data[$i + 1];
                } else {
                    $map[$data[$i]] = 0;
                }
            }
        }

        return $map;
    }

    /**
     * @param $key
     * @param $field
     * @param int $increment
     */
    private function hincyby($key,$field ,$increment = 1)
    {
        $isExist = (boolean)$this->redis->exists($key);
        if ($isExist === false) {
            $this->redis->hmset($key, $field, $increment);
            $this->redis->expire($key, 86400 * 2);
        } else {
            $this->redis->hincrby($key, $field, 1);
        }
    }


    /**
     * 生成器逐行读取大文件
     * @param $file
     * @return \Generator
     */
    private static function readLine($file)
    {
        $handle = fopen($file, 'r');

        while (!feof($handle)) {
            yield trim(fgets($handle));
        }

        fclose($handle);
    }


}