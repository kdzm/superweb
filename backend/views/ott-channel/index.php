<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use yii\bootstrap\Modal;
/* @var $this yii\web\View */
/* @var $searchModel common\models\search\OttChannelSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('backend', 'Channel List');
$this->params['breadcrumbs'][] = ['label' => $mainClass->zh_name? $mainClass->zh_name:$mainClass->name, 'url' => Url::to(['main-class/index'])];
$this->params['breadcrumbs'][] = ['label' => $subClass->zh_name?$subClass->zh_name:$subClass->name, 'url' => Url::to(['sub-class/index', 'main-id' => $mainClass->id])];
$this->params['breadcrumbs'][] = $this->title;
?>

<style>
    .grid-view th{text-align: center;}
    .grid-view td {
        text-align: center;
        vertical-align: middle !important;
    }
</style>

<div class="ott-channel-index">
    <?php

            echo GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'tableOptions' => [
                    'class' => 'table table-bordered table-hover',
                ],
                'pager' => [
                    'class' => 'common\widgets\goPager',
                    'firstPageLabel' => Yii::t('backend', 'First Page'),
                    'lastPageLabel' => Yii::t('backend', 'Last Page'),
                    'go' => true
                ],
                'options' => ["class" => "grid-view","style"=>"overflow:auto", "id" => "grid"],

                'columns' => [
                    [
                        "class" => "yii\grid\CheckboxColumn",
                        "name" => "id",
                    ],

                    ['class' => 'yii\grid\SerialColumn'],

                    [
                        'attribute' => 'image',
                        'filter' => false,
                        'format' => ['image',['width'=>80,'height'=>60]],
                        'options' => ['style' => 'width:85px;'],
                        'value' => function($model) {
                            return \common\components\Func::getAccessUrl($model->image);
                        }
                    ],

                    [
                        'attribute' => 'name',
                        'contentOptions' => ['class' => 'ajax-td'],
                        'options' => ['style' => 'min-width:120px;'],
                        'format' => 'raw',

                    ],
                    [
                        'label' => Yii::t('backend', 'EPG'),
                        'options' => ['style' => 'min-width:120px;'],
                        'format' => 'raw',
                        'value' => function($model) {
                            if (!is_null($model->relatedParade)) {
                                return \common\widgets\frameButton::widget([
                                        'url' => Url::to(['parade/list-channel','name' => $model->name]),
                                        'content' => Yii::t('backend', 'EPG'),
                                        'icon' => 'fa-info'
                                ]);
                            }

                            return '-';
                        }
                    ],

                    [
                        'attribute' => 'channel_number',

                        'options' => ['style' => 'width:70px;'],
                        'filter' => false
                    ],
                    [
                        'attribute' => 'sort',
                        'filter' => false,
                        'contentOptions' => ['class' => 'ajax-td'],
                        'options' => ['style' => 'width:60px;'],
                        'format' => 'raw',
                        'value' => function($model) {
                            return \yii\bootstrap\Html::textInput('sort', $model->sort, [
                                'class' => 'form-control ajax-update',
                                'data-id' => $model->id,
                                'field' => 'sort',
                                'old-value' => $model->sort
                            ]);
                        }
                    ],

                    [
                        'attribute' => 'use_flag',
                        'headerOptions' =>['class' => 'col-md-1'],
                        'filter' => false,
                        'format' => 'raw',
                        'value' => function($model) {
                            return \common\widgets\switchInput\SwitcherInputWidget::widget([
                                'id' => $model->id,
                                'field' => 'use_flag',
                                'url' => \yii\helpers\Url::to(['ott-channel/update', 'id' => $model->id]),
                                'defaultCheckedStatus' => $model->use_flag,
                                'successTips' => '操作成功',
                                'errorTips'   => '操作失败'
                            ]);
                        }
                    ],

                    [
                        'class' => 'common\grid\MyActionColumn',
                        'size' => 'btn-sm',
                        'template' => '{channel} &nbsp;|&nbsp;{more} {update} {delete}',
                        'buttons' => [

                            'update' => function($url, $model) {
                                return \common\widgets\frameButton::widget([
                                        'content' => '编辑',
                                        'url' => $url,
                                        'options' => ['class' => 'btn btn-primary fa fa-edit',]
                                ]);

                            },
                            'channel' => function($url, $model, $key) {
                                return Html::a("&nbsp;&nbsp;<i class='glyphicon glyphicon-link'></i>". Yii::t('backend', 'Link')."&nbsp;&nbsp;", null, [
                                    'class' => 'btn btn-success btn-sm load-link',
                                    'data-toggle' => 'modal',
                                    'data-target' => '#links-modal',
                                    'data-id' => $model->id,
                                ]);
                            },
                            'push-banner' => function($url, $model, $key) {
                                return Html::a("&nbsp;<i class='glyphicon glyphicon-picture'></i>&nbsp;", Url::to(Url::to(['ott-banner/create', 'channel_id' => $model->id])), [
                                    'class' => 'btn btn-default btn-sm'
                                ]);
                            },
                            'push-recommend' => function($url, $model, $key) {
                                $class = $model->is_recommend ? 'btn-warning' : 'btn-default';

                                return Html::a("&nbsp;<i class='glyphicon glyphicon-thumbs-up'></i>&nbsp;", Url::to(Url::to(['ott-channel/update', 'id' => $model->id, 'field'=>'is_recommend'])), [
                                    'class' => 'btn btn-sm ' . $class
                                ]);
                            },

                            'more' => function($url, $model) {
                                return Html::a(Html::tag('i', ' 更多', [
                                    'class' => 'fa fa-cog'
                                ]),null, [
                                    'class' => 'btn btn-info btn-sm btn-more',
                                    'data-toggle' => 'modal',
                                    'data-target' => '#more-modal',
                                    'data-id'     => $model->id,
                                    'data-banner-url' => Url::to(['ott-banner/create', 'channel_id' => $model->id]),
                                    'data-recommend-url' => Url::to(['ott-channel/update', 'id' => $model->id, 'field'=>'is_recommend']),
                                    'title' => '更多操作'
                                ]);
                            }

                        ],
                        'options' => [ 'style' => 'width:350px;'],
                        'visibleButtons' => [
                                'delete' => Yii::$app->user->can('ott-channel/delete')
                        ]
                    ],
                ],
            ]);

    ?>

 </div>
<div>

<?= \common\widgets\frameButton::widget(['options' => ['class' => 'btn btn-success'],'content' => Yii::t('backend', 'Create'), 'url' => Url::to(['create','sub-id' => $subClass->id])]) ?>

<?= Html::a(Yii::t('backend', 'Rearrange channel numbers'), ['sub-class/reset-number','main_class_id' => $mainClass->id], ['class' => 'btn btn-primary']) ?>

<?= Html::button(Yii::t('backend', 'Batch Deletion'),['class' => 'gridview btn btn-danger' ]) ?>

<?= Html::a(Yii::t('backend', 'Go Back'), Url::to(['sub-class/index', 'main-id' => $mainClass->id]), ['class' => 'btn btn-default']) ?>

</div>

<?= $this->render('_links'); ?>
<?= $this->render('_more'); ?>





