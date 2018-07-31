<?php
/**
 * Created by PhpStorm.
 * User: lychee
 * Date: 2018/4/10
 * Time: 18:21
 */

namespace backend\models;

use Yii;
use console\components\CronParser;


/**
 * This is the model class for table "sys_crontab".
 *
 * @property int $id
 * @property string $name 定时任务名称
 * @property string $route 任务路由
 * @property string $crontab_str crontab格式
 * @property int $switch 任务开关 0关闭 1开启
 * @property int $status 任务运行状态 0正常 1任务报错
 * @property string $last_rundate 任务上次运行时间
 * @property string $next_rundate 任务下次运行时间
 * @property string $execmemory 任务执行消耗内存(单位/byte)
 * @property string $exectime 任务执行消耗时间
 */
class Crontab extends \yii\db\ActiveRecord
{
    const SWITCH_ON = 1;
    const SWITCH_OFF = 0;

    const NORMAL = 0;
    const READY = 1;
    const RUNNING = 2;
    const ERROR = 3;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sys_crontab';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'route', 'crontab_str'], 'required'],
            [['last_rundate', 'next_rundate'], 'safe'],
            [['execmemory', 'exectime'], 'number'],
            [['name', 'route', 'crontab_str'], 'string', 'max' => 50],
            [['switch'], 'string', 'max' => 1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => Yii::t('backend', 'Name'),
            'route' => Yii::t('backend', 'Route'),
            'crontab_str' => Yii::t('backend', 'Crontab Format'),
            'switch' => Yii::t('backend', 'Switch'),//'0关闭 1开启',
            'status' => Yii::t('backend', 'Status'),//'任务运行状态 0正常 1任务报错',
            'last_rundate' => Yii::t('backend', 'Last Run'),//'上次运行',
            'next_rundate' => Yii::t('backend', 'Next Run'),//'下次运行',
            'execmemory' => Yii::t('backend', 'Memory consumption'), //'内存消耗(b)',
            'exectime' => Yii::t('backend', 'Time Consuming'),
            'switchText' => Yii::t('backend', 'Switch'),
            'statusText' => Yii::t('backend', 'Operating status')
        ];
    }

    /**
     * switch字段的文字映射
     * @var array
     */
    private $switchTextMap = [
        0 => '关闭',
        1 => '开启',
    ];

    /**
     * status字段的文字映射
     * @var array
     */
    private $statusTextMap = [
        self::NORMAL => '正常',
        self::READY => '任务就绪',
        self::RUNNING => '正在运行',
        self::ERROR => '任务保存'
    ];

    public function getSwitchItems()
    {
        return $this->switchTextMap;
    }

    /* public static function getDb()
     {
         #注意!!!替换成自己的数据库配置组件名称
         return Yii::$app->tfbmall;
     }*/

    /**
     * 获取switch字段对应的文字
     * @return string
     */
    public function getSwitchText()
    {
        if(!isset($this->switchTextMap[$this->switch])) {
            return '';
        }
        return $this->switchTextMap[$this->switch];
    }

    /**
     * 获取status字段对应的文字
     * @return string
     */
    public function getStatusText()
    {
        if(!isset($this->statusTextMap[$this->status])) {
            return '';
        }
        return $this->statusTextMap[$this->status];
    }

    /**
     * 计算下次运行时间
     */
    public function getNextRunDate()
    {
        if (!CronParser::check($this->crontab_str)) {
            throw new \Exception("格式校验失败: {$this->crontab_str}", 1);
        }
        return CronParser::formatToDate($this->crontab_str, 1)[0];
    }

}
