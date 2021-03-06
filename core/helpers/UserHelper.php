<?php

namespace core\helpers;

use core\entities\User\User;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use Yii;

class UserHelper
{
    public static function statusList(): array
    {
        return [
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_WAIT => 'Wait',
        ];
    }

    public static function statusName($status): string
    {
        return ArrayHelper::getValue(self::statusList(), $status);
    }

    public static function statusLabel($status): string
    {
        switch ($status) {
            case User::STATUS_WAIT:
                $class = 'label label-default';
                break;
            case User::STATUS_ACTIVE:
                $class = 'label label-success';
                break;
            default:
                $class = 'label label-default';
        }
        return Html::tag('span', ArrayHelper::getValue(self::statusList(), $status), [
                'class' => $class,
            ]);
    }

    public static function getOrderFormOfUserType(): string
    {
            switch (User::findOne(Yii::$app->user->id)->type ?? null) {
                case User::TYPE_INDIVIDUAL : $customerFormName = 'customer-form';
                    break;
                case User::TYPE_COMPANY : $customerFormName = 'company-form';
                    break;
                case User::TYPE_ADMIN : $customerFormName = 'simple-form';
                    break;
                default : $customerFormName = 'customer-form';
            }
        return $customerFormName;
    }
}