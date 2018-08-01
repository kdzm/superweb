<?php

namespace common\models;

use backend\components\MyRedis;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "ott_main_class".
 *
 * @property int $id
 * @property string $name 名字
 * @property string $zh_name 中文名字
 * @property string $description
 * @property string $icon 图标
 * @property string $icon_hover 图标高亮
 * @property string $icon_bg 大图标
 * @property string $icon_bg_hover 大图表非高亮
 * @property string $sort 排序
 * @property string $is_charge 是否收费
 * @property string $price 价格
 * @property string $use_flag 开关
 */
class MainClass extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ott_main_class';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'zh_name'], 'required'],
            [['name', 'zh_name', 'description', 'icon', 'icon_bg','icon_hover', 'icon_bg', 'icon_bg_hover'], 'string', 'max' => 255],
            [['sort'], 'string', 'max' => 3],
            ['price', 'number', 'integerOnly' => false, 'min' => 0],
            ['is_charge', 'boolean'],
            ['use_flag', 'safe'],
            ['use_flag', 'default', 'value' => '1']
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
            'zh_name' => Yii::t('backend', 'Chinese Name'),
            'description' => Yii::t('backend', 'Description'),
            'icon' => Yii::t('backend', 'Icon'),
            'icon_hover' => Yii::t('backend', 'Icon(hover)'),
            'icon_bg' => Yii::t('backend', 'Big icon'),
            'icon_bg_hover' => Yii::t('backend', 'Big icon(hover)'),
            'sort' => Yii::t('backend', 'Sort'),
            'is_charge' => Yii::t('backend', 'Is Charge'),
            'price' => Yii::t('backend', 'Price'),
            'use_flag' => Yii::t('backend', 'Switch')
        ];
    }

    /**
     * 与子类的 关联关系
     * @return \yii\db\ActiveQuery
     */
    public function getSub($where = null)
    {
        $query = $this->hasMany(SubClass::className(), ['main_class_id' => 'id']);
        if ($where) {
            $query->where($where);
        }
        return $query;
    }

    public function getSubChannel()
    {
        return $this->hasMany(OttChannel::className(), ['sub_class_id' => 'id'])
                    ->via('sub');
    }

    public function getSubLink()
    {
        return $this->hasMany(OttLink::className(), ['channel_id' => 'id'])
                    ->via('subChannel');
    }

    public function beforeDelete()
    {
       $subClass = $this->sub;
       if (!empty($subClass)) {
           foreach ($subClass as $class) {
               $class->delete();
           }
       }
       return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $redis = MyRedis::init(MyRedis::REDIS_PROTOCOL);
        $redis->del("OTT_CLASSIFICATION");
        return true;
    }

    public static function getDropdownList()
    {
        return ArrayHelper::map(self::find()->all(), 'id', 'name');
    }
}
