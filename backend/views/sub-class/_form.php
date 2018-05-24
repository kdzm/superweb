<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\Url;
/* @var $this yii\web\View */
/* @var $model common\models\SubClass */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="sub-class-form">

    <?php $form = ActiveForm::begin([
        'id' => 'form-id',
        'enableAjaxValidation' => true,
        'validationUrl' => Url::toRoute(['validate-form']),
    ]); ?>

    <div class="col-md-6">
        <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
    </div>

    <div class="col-md-6">
        <?= $form->field($model, 'zh_name')->textInput(['maxlength' => true]) ?>
    </div>

    <div class="col-md-6">
        <?= $form->field($model, 'sort')->textInput() ?>
    </div>

    <div class="col-md-6">
        <?= $form->field($model, 'use_flag')->dropDownList(['不可用', '可用']) ?>
    </div>

    <div class="col-md-12">
        <?= $form->field($model, 'keyword')->textInput(['maxlength' => true]) ?>
        <?= $form->field($model, 'main_class_id')->hiddenInput()->label(false) ?>
        <div class="form-group">
            <?= Html::submitButton('保存', ['class' => 'btn btn-success']) ?>
            <?= Html::a('返回', Url::to(['sub-class/index', 'main-id'=>$model->mainClass->id]), ['class' => 'btn btn-default']) ?>

        </div>
    </div>
    <?php ActiveForm::end(); ?>

</div>