<?php
/**
 * Created by PhpStorm.
 * User: lychee
 * Date: 2018/4/19
 * Time: 15:01
 */

namespace console\controllers;

use backend\models\MajorEvent;
use backend\models\Parade;
use common\models\OttChannel;
use console\collectors\event\CBA;
use console\jobs\ParadeJob;
use console\collectors\event\NBA;
use console\collectors\event\zhiboba;
use console\collectors\parade\beginsport;
use console\collectors\parade\espn;
use console\collectors\parade\eurosport;
use console\collectors\parade\manmankan;
use console\collectors\parade\sfrsport;
use console\collectors\parade\skysport;
use console\collectors\parade\sportnet;
use console\collectors\parade\tsn;
use console\collectors\parade\vn;
use yii\console\Controller;


/**
 * 预告控制器
 * Class ParadeController
 * @package console\controllers
 */
class ParadeController extends Controller
{

    public function actionHelp()
    {
        $list = [
            'sky','tsn','espn','sport','sfr','euro', 'begin', 'cctv5','create-cache'
        ];
        
        foreach ($list as $action) {
            $this->stdout($action . PHP_EOL);
        }
    }

    public function actionCreateCache()
    {
        ParadeJob::generateCache();
    }

    public function clearExpire()
    {
        $date = date('Y-m-d' ,strtotime('-9 day'));
        Parade::deleteAll(['<=', 'parade_date', $date]);
    }

    public function actionCba()
    {
        try {
            $live = new CBA();
            $live->start();
            $this->actionMatchChannel();

        } catch (\Exception $e) {}
    }

    public function actionNba()
    {
        try {
            $live = new NBA();
            $live->start();
            $this->actionMatchChannel();

        } catch (\Exception $e) {}
    }

    public function actionZhiboba()
    {
        try {
            $live = new zhiboba();
            $live->start();
        } catch (\Exception $e) {}
    }

    public function actionCollect()
    {
        $this->clearExpire();
        //$this->actionVn();
        $this->actionSky();
        $this->actionTsn();
        $this->actionEspn();
        $this->actionSport();
        $this->actionSfr();
        $this->actionEuro();
        $this->actionBegin();
        $this->actionCctv5();

    }

    public function actionVn()
    {
       try {
           $vn = new vn();
           $vn->start();
       } catch (\Exception $e) {}
    }

    public function actionSky()
    {
        try {
            $sky = new skysport();
            $sky->start();
        } catch (\Exception $e) {}
    }

    public function actionTsn()
    {
       try {
           $tsn = new tsn();
           $tsn->start();
       } catch (\Exception $e) {}
    }

    /**
     *
     */
    public function actionEspn()
    {
        try {
            $espn = new espn();
            $espn->start();
        } catch (\Exception $e) {}
    }

    /**
     *
     */
    public function actionSport()
    {
       try {
           $sport = new sportnet();
           $sport->start();
       }catch (\Exception $e) {}
    }

    public function actionSfr()
    {
       try {
           $sfr = new sfrsport();
           $sfr->start();
       } catch (\Exception $e) {}
    }

    public function actionEuro()
    {
       try {
           $euro = new eurosport();
           $euro->start();
       } catch (\Exception $e) {}
    }

    public function actionBegin()
    {
        try {
            $begin = new beginsport();
            $begin->start();
        } catch (\Exception $e) {}
    }
    
    public function actionCctv5()
    {
        try {
            $kan = new manmankan();
            $kan->start();
        } catch (\Exception $e) {}
    }

    private function pushParade($channel_name, $date, $data)
    {
        $parade = Parade::findOne(['parade_date' => $date, 'channel_name' => $channel_name]);
        if (!is_null($parade)) {
            $paradeData = $parade->getData();
            $paradeData = array_merge($paradeData, $data);

            array_multisort(array_column($paradeData,'parade_timestamp'), SORT_ASC,$paradeData);

            $parade->parade_data = json_encode($paradeData);
            $parade->save(false);
        }
    }

    private function copyData($channel_name,$data)
    {
        $insertData = [];

        foreach ($data as $value) {
            $readyTimestamp = $value['parade_timestamp'] - 3600 * 4;
            $beginTime = date('H:i', $value['parade_timestamp']);
            $data = [
                'parade_timestamp' => $readyTimestamp,
                'parade_time' => date('H:i:s', $readyTimestamp),
                'parade_name' => $value['parade_name'] . "(readying，begin：{$beginTime})"
            ];

            $date = date('Y-m-d', $readyTimestamp);
            $insertData[$date][] = $data;
        }

        if (!empty($insertData)) {
            foreach ($insertData as $date => $data) {
                $this->pushParade($channel_name, $date, $data);
            }
        }

        return false;
    }

    public function actionReadyRace()
    {
        // 遍历所有预告 删除掉所有ready
        $parades = Parade::find()->where(['like','channel_name','sport'])->all();
        foreach ($parades as $parade) {
            $parade_data = $parade->getData();
            if (!empty($parade_data)) {
                foreach ($parade_data as $key => $value) {
                    if (strpos($value['parade_name'], 'readying，begin')) {
                        unset($parade_data[$key]);
                    }
                }

                $parade->parade_data = json_encode($parade_data);
                $parade->save(false);
            }
        }
        
        $parades = Parade::find()->where(['like','channel_name','sport'])->all();
        foreach ($parades as $parade) {
            $parade_data = $parade->getData();
            if (!empty($parade_data)) {
                $this->copyData($parade->channel_name, $parade_data);
            }
        }
    }

    public function actionMatchChannel()
    {
        // 频道数组 放频道会播放的节目
        $channelArr = [];

        // 取出今天的主要赛事给他们匹配上频道
        $tasks = ['NBA常规赛', 'CBA常规赛'];
        foreach ($tasks as $eventTitle) {

            $basic = strtotime('today');
            for ($day = 0; $day <= 7; $day++) {
                $start = $basic + $day * 86400;
                $end = $start + 86400;
                $events = MajorEvent::getEventByTitle($eventTitle, $start, $end);

                if (!is_null($events)) {
                    foreach ($events as $num => $event) {

                        if ($match = OttChannel::getChannelParadeInfo('sport', 'NBA', "sport{$num}", "CN")) {
                            // 主要赛事 频道绑定
                            $event->match_data = json_encode([$match]);
                            $event->save(false);

                            $date = $event->getDate();
                            $channelArr[$match['channel_name']][$date][] = [
                                'parade_time' => $event->getTime(),
                                'parade_name' => $event->getBrief(),
                                'channel_id' => $match['channel_true_id'],
                            ];
                        }
                    }
                }
            }
        }

        if (!empty($channelArr)) {
            foreach ($channelArr as $channelName => $dateArr) {
                foreach ($dateArr as $date => $parade) {
                     $paradeObject = new Parade();
               
                     $paradeObject->parade_data = json_encode($parade);
                     $paradeObject->parade_date = $date;
                     $paradeObject->upload_date = date('Y-m-d');
                     $paradeObject->channel_name = $channelName;
                     $paradeObject->channel_id = $parade[0]['channel_id'];
                     $paradeObject->source = '匹配添加';

                     $paradeObject->save(false);
                }
            }
        }

    }
}