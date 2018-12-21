<?php
/**
 * Created by PhpStorm.
 * User: lychee
 * Date: 2018/12/7
 * Time: 14:42
 */

namespace console\collectors\local;

use Yii;
use yii\base\Model;
use common\models\Type;
use common\models\Vod;
use console\collectors\profile\ProfilesSearcher;
use console\collectors\common;
use common\components\FileHelper;
use yii\helpers\Console;

class VodCollector extends common
{
    /**
     * @var Model 接收数据的模型
     */
    public $model;
    /**
     * @var string 映射路径 /home/newpo/pinyin/zongyi/
     */
    public $dir;
    /**
     * @var string nginx访问路径 如  /vod/zongyi
     */
    public $playpath;
    /**
     * @var string 类型 如movie cartoon
     */
    public $type;
    /**
     * @var string 地区
     */
    public $area;
    /**
     * @var string 语言
     */
    public $language;

    /**
     * @var string 分类
     */
    public $genre;

    public $address = 'http://hvod.imirun.net:8080';

    public function __construct(Model $model, $options)
    {
        if (empty(Yii::$app->params['vod_play_host'])) {
            Console::error("请到console/config/params-local中配置CDN播放域名 'vod_play_host' => 'http://demo.com'");
            exit;
        }

        $this->model = $model;
        $fields = ['dir', 'playpath', 'type','area','language'];
        foreach ($fields as $field) {
            if (!isset($options[$field])) {
                throw new \Exception("{$field} 必须配置");
            } else {
                $this->$field = $options[$field];
            }
        }

        if (isset($options['genre'])) {
            $this->genre = $options['genre'];
        }

    }

    public function doCollect()
    {
        if (is_dir($this->dir) == false) {
            $this->color("不存在目录: {$this->dir}", 'ERROR');
            return false;
        }

        $this->getFileList($this->dir);

        return true;
    }

    protected function getCover($path, $fileName)
    {
        return 'http://' . (Yii::$app->params['serverDomain']?:Yii::$app->params['serverIP'] ) .
                '/' . basename($this->playpath) . "/". basename($path) . "/" . $fileName;
    }

    protected function getProfile($path)
    {
        if (!is_dir($path)) {
            return false;
        }

        $list = scandir($path);
        foreach ($list as $fileName) {
            preg_match('/(\S)+(?=(.jepg|.jpg|.png))/', $fileName, $match);
            if (isset($match[0]) && !empty($match[0])) {
                return [
                    'vod_name' => $match[0],
                    'vod_pic' => $this->getCover($path, $fileName),
                    'vod_area' => $this->area,
                    'vod_language' => $this->language
                ];
            }
        }

        return false;
    }

    protected function getEpisodeNumber($fileName)
    {
        preg_match('/\d+/', str_replace('mp4','', $fileName), $episodeNum);

        if ($this->type == 'movie') {
            return 1;
        } else {
            return $episodeNum[0]??1;
        }
    }

    protected function getEpisodes($path)
    {
        if (!is_dir($path)) {
            return false;
        }

        $episodes = [];
        $list = scandir($path);

        foreach ($list as $fileName) {
            if (!in_array($fileName, ['.', '..'])) {
                preg_match('/.*mp4/', $fileName, $linkMatch);
                if (isset($linkMatch[0])) {
                    $fileName = $linkMatch[0];
                    $episodes[] =  [
                        'title'   => '全集',
                        'episode' => $this->getEpisodeNumber($fileName),
                        'url'     => $this->getLink($path, $fileName),
                    ];
                }
            }
        }

       return $episodes;
    }

    protected function getLink($path,$fileName)
    {
        $dirName = basename($path);
        return Yii::$app->params['vod_play_host'] . $this->playpath . '/'. $dirName .'/' . $fileName . '/index.m3u8';
    }

    /**
     * @param $directory
     * @return array
     */
    protected function getFileList($directory)
    {
        $mediaArr = [];
        $list = FileHelper::scandir($directory);
        if (!empty($list)) {
            foreach ($list as $fileName) {
                $path = $directory . $fileName;
                $data = $this->getProfile($path);
                $data['links'] = $this->getEpisodes($path);

                if (!empty($data) && isset($data['vod_name']) && !empty($data['links']) && is_null(Vod::findOne(['vod_name' => $data['vod_name']]))) {

                    if ($this->type == 'movie' && $profile = ProfilesSearcher::search($data['vod_name'], ['language' => 'zh-CN'])) {
                        $profile['vod_language'] = $data['vod_language'];
                        $profile['vod_area'] = $data['vod_area'];
                        $data = array_merge($data, $profile);
                        $data['vod_fill_flag'] = 1;

                    } else if ($this->type == 'Serial') {
                         $data['genre'] = $this->genre;
                    }

                    if (isset($data['vod_gold']) && !empty($data['vod_gold']) &&
                        isset($data['vod_year']) && !empty($data['vod_year'])
                    ) {
                        if ($data['vod_gold']*10 > 80 ) {
                            $data = $this->setGenre($data, '最热');
                            $data['is_top'] = 1;
                        }
                    }

                    if ($this->genre) {
                        $data = $this->setGenre($data, $this->genre);
                    }

                    if (strpos($fileName, 'yueyuban') !== false) {
                        $data = $this->setGenre($data, '粤语');
                    }

                    if (isset($data['vod_year']) && $data['vod_year'] >= 2018) {
                       $data = $this->setGenre($data, '最新');
                    }

                    $data['vod_language'] = $this->language;
                    $data['vod_area']     = $this->area;

                    $mediaArr[] = $data;
                    $this->saveToDb($data);
                }
            }
        }

        return $mediaArr;
    }

    private function setGenre($data,$value)
    {
        if (isset($data['vod_type'])) {
            $data['vod_type'] .= ",{$value}";
        } else {
            $data['vod_type'] = "{$value}";
        }

        return $data;
    }

    protected function saveToDb($data)
    {
        try {
            $this->createVod($data, 'local');
        } catch (\Exception $e) {
            $this->color("File:" . $e->getFile(),'ERROR');
            $this->color("Line:" . $e->getLine(),'ERROR');
            $this->color("Message:" . $e->getMessage(),'ERROR');
        }
    }
}