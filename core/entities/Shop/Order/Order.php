<?php
/**
 * Created by PhpStorm.
 * User: volynets
 * Date: 12.09.17
 * Time: 17:08
 */

namespace core\entities\Shop\Order;

use core\entities\Shop\PaymentMethod\PaymentMethod;
use core\forms\Shop\Order\PaymentForm;
use lhs\Yii2SaveRelationsBehavior\SaveRelationsBehavior;
use core\entities\Shop\DeliveryMethod\DeliveryMethod;
use core\entities\User\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * @property int $id
 * @property int $created_at
 * @property int $user_id
 * @property int $delivery_method_id
 * @property int $payment_method_id
 * @property string $delivery_method_name
 * @property string $payment_method_name
 * @property int $delivery_cost
 * @property string $payment_method
 * @property int $cost
 * @property int $note
 * @property int $current_status
 * @property string $cancel_reason
 * @property CustomerData $customerData
 * @property DeliveryData $deliveryData
 * @property DeliveryMethod $deliveryMethod
 * @property PaymentMethod $paymentMethod
 *
 * @property OrderItem[] $items
 * @property Status[] $statuses
 */
class Order extends ActiveRecord
{
    public $customerData;
    public $deliveryData;
    public $statuses = [];

    public static function create($userId, CustomerData $customerData, array $items, $cost, $note): self
    {
        $order = new static();
        $order->user_id = $userId;
        $order->customerData = $customerData;
        $order->items = $items;
        $order->cost = $cost;
        $order->note = $note;
        $order->created_at = time();
        $order->addStatus(Status::NEW);
        return $order;
    }

    public function edit(CustomerData $customerData, $note): void
    {
        $this->customerData = $customerData;
        $this->note = $note;
    }

    public function setDeliveryInfo(DeliveryMethod $method, DeliveryData $deliveryData): void
    {
        $this->delivery_method_id = $method->id;
        $this->delivery_method_name = $method->name;
        $this->delivery_cost = $method->cost;
        $this->deliveryData = $deliveryData;
    }

    public function setPaymentInfo(PaymentMethod $method): void
    {
        $this->payment_method_id = $method->id;
        $this->payment_method_name = $method->name;
    }

    public function pay($method): void
    {
        if ($this->isPaid()) {
            throw new \DomainException('Order is already paid.');
        }
        $this->payment_method = $method;
        $this->addStatus(Status::PAID);
    }

    public function send(): void
    {
        if ($this->isSent()) {
            throw new \DomainException('Order is already sent.');
        }
        $this->addStatus(Status::SENT);
    }

    public function complete(): void
    {
        if ($this->isCompleted()) {
            throw new \DomainException('Order is already completed.');
        }
        $this->addStatus(Status::COMPLETED);
    }

    public function cancel($reason): void
    {
        if ($this->isCancelled()) {
            throw new \DomainException('Order is already cancelled.');
        }
        $this->cancel_reason = $reason;
        $this->addStatus(Status::CANCELLED);
    }

    public function getTotalCost(): int
    {
        return $this->cost + $this->delivery_cost;
    }

    public function canBePaid(): bool
    {
        return $this->isNew();
    }

    public function isNew(): bool
    {
        return $this->current_status == Status::NEW;
    }

    public function isPaid(): bool
    {
        return $this->current_status == Status::PAID;
    }

    public function isSent(): bool
    {
        return $this->current_status == Status::SENT;
    }

    public function isCompleted(): bool
    {
        return $this->current_status == Status::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->current_status == Status::CANCELLED;
    }

    private function addStatus($value): void
    {
        $this->statuses[] = new Status($value, time());
        $this->current_status = $value;
    }

    ##########################

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getDeliveryMethod(): ActiveQuery
    {
        return $this->hasOne(DeliveryMethod::class, ['id' => 'delivery_method_id']);
    }

    public function getPaymentMethod(): ActiveQuery
    {
        return $this->hasOne(PaymentMethod::class, ['id' => 'payment_method_id']);
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'id']);
    }

    ##########################

    public static function tableName(): string
    {
        return '{{%shop_orders}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => SaveRelationsBehavior::className(),
                'relations' => ['items'],
            ],
        ];
    }

    public function transactions(): array
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }

    public function afterFind(): void
    {
        $this->statuses = array_map(function ($row) {
            return new Status(
                $row['value'],
                $row['created_at']
            );
        }, Json::decode($this->getAttribute('statuses_json')));

        $this->customerData = new CustomerData(
            $this->getAttribute('customer_name'),
            $this->getAttribute('customer_last_name'),
            $this->getAttribute('customer_email'),
            $this->getAttribute('customer_phone')
        );

        $this->deliveryData = new DeliveryData(
            $this->getAttribute('delivery_address')
        );

        parent::afterFind();
    }

    public function beforeSave($insert): bool
    {
        $this->setAttribute('statuses_json', Json::encode(array_map(function (Status $status) {
            return [
                'value' => $status->value,
                'created_at' => $status->created_at,
            ];
        }, $this->statuses)));

        $this->setAttribute('customer_name', $this->customerData->firstName);
        $this->setAttribute('customer_last_name', $this->customerData->lastName);
        $this->setAttribute('customer_email', $this->customerData->email);
        $this->setAttribute('customer_phone', $this->customerData->phone);

        $this->setAttribute('delivery_address', $this->deliveryData->address);

        return parent::beforeSave($insert);
    }
}