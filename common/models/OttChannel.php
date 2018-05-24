<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "ott_channel".
 *
 * @property int $id
 * @property int $sub_class_id 关联id
 * @property string $name 名称
 * @property string $zh_name 中文名称
 * @property string $keywords 关键字
 * @property int $sort 排序
 * @property int $use_flag
 * @property int $channel_number 序列号
 * @property string $image 图标
 * @property string $alias_name 别名
 */
class OttChannel extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ott_channel';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['sub_class_id', 'name', 'zh_name', 'keywords'], 'required'],
            [['sub_class_id', 'sort', 'use_flag', 'channel_number'], 'integer'],
            [['name', 'zh_name', 'keywords'], 'string', 'max' => 255],
            [['image'], 'string', 'max' => 50],
            [['alias_name'], 'string', 'max' => 100],
            [['sort'], 'default', 'value' => '0'],
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
            'sub_class_id' => '关联id',
            'name' => '名称',
            'zh_name' => '中文名称',
            'keywords' => '关键字',
            'sort' => '排序',
            'use_flag' => '是否可用',
            'channel_number' => '频道号',
            'image' => '图标',
            'alias_name' => '别名',
        ];
    }

    /**
     * 获取上级分类
     * @return \yii\db\ActiveQuery
     */
    public function getSubClass()
    {
        return $this->hasOne(SubClass::className(), ['id' => 'sub_class_id']);
    }

    public function getOwnLink($where = null)
    {
        $query = $this->hasMany(OttLink::className(), ['channel_id' => 'id']);
        if ($where) {
            $query->where($where);
        }

        return $query;
    }

    public function beforeSave($insert)
    {
        parent::beforeSave($insert);
        //处理最大值
        if ($this->isNewRecord) {
            $this->channel_number = self::find()->where(['sub_class_id' => $this->sub_class_id])->max('channel_number') + 1;
        }

        return true;
    }

    public function beforeDelete()
    {
        OttLink::deleteAll(['channel_id' => $this->id]);
        return true;
    }

}