<?php
/**
 * Created by PhpStorm.
 * User: volynets
 * Date: 09.10.17
 * Time: 11:01
 */

namespace api\controllers\v1\shop;

use core\cart\Cart;
use core\forms\Shop\Order\OrderForm;
use core\useCases\Shop\OrderService;
use Yii;
use yii\helpers\Url;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;

class CheckoutController extends Controller
{
    private $cart;
    private $service;

    public function __construct($id, $module, OrderService $service, Cart $cart, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->cart = $cart;
        $this->service = $service;
    }

    public function verbs(): array
    {
        return [
            'index' => ['POST'],
        ];
    }

    public function actionIndex()
    {
        $form = new OrderForm($this->cart->getWeight());

        $form->load(Yii::$app->request->getBodyParams(), '');

        if ($form->validate()) {
            try {
                $order = $this->service->checkout(Yii::$app->user->id, $form);
                $response = Yii::$app->getResponse();
                $response->setStatusCode(204);
                $response->getHeaders()->set('Location', Url::to(['shop/order/view', 'id' => $order->id], true));
                return [];
            } catch (\DomainException $e) {
                throw new BadRequestHttpException($e->getMessage(), null, $e);
            }
        }

        return $form;
    }
}