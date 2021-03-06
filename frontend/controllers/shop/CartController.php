<?php
/**
 * Created by PhpStorm.
 * User: volynets
 * Date: 11.09.17
 * Time: 9:08
 */

namespace frontend\controllers\shop;

use core\cart\Cart;
use core\entities\User\User;
use core\entities\User\UserIndividual;
use core\forms\Shop\Cart\AddToCartForm;
use core\forms\Shop\Cart\FastAddToCartForm;
use core\forms\Shop\Cart\FileAddToCartForm;
use core\helpers\LocationHelper;
use core\readModels\Shop\ProductReadRepository;
use core\readModels\UserReadRepository;
use core\readModels\WarehouseReadRepository;
use core\services\import\Cart\Reader;
use core\useCases\Shop\CartService;
use core\useCases\Shop\OrderService;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use core\forms\Shop\Order\OrderForm;
use yii\web\Request;
use yii\web\Response;
use yii\web\UploadedFile;

class CartController extends Controller
{
    public $layout = 'blank';

    private $products;
    private $service;
    private $orderService;
    private $warehouses;
    private $users;
    private $cart;

    public function __construct(
        $id,
        $module,
        CartService $service,
        OrderService $orderService,
        Cart $cart,
        ProductReadRepository $products,
        WarehouseReadRepository $warehouses,
        UserReadRepository $users,
        $config = []
    )
    {
        parent::__construct($id, $module, $config);
        $this->products = $products;
        $this->service = $service;
        $this->orderService = $orderService;
        $this->warehouses = $warehouses;
        $this->users = $users;
        $this->cart = $cart;
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'quantity' => ['POST'],
                    'remove' => ['POST'],
                    'remove-modification' => ['POST'],
                ],
            ],
            'contentNegotiator' => [
                'class' => ContentNegotiator::className(),
                'only' => ['add', 'remove', 'quantity', 'remove-modification'],
                'formatParam' => '_format',
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
        ];
    }

    /**
     * @param null $delivery_id
     * @return string
     */
    public function actionIndex($delivery_id = null)
    {
        $cart = $this->service->getCart();
        $fileForm = new FileAddToCartForm();
        $form = new OrderForm($this->cart->getWeight(), $delivery_id);


        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $order = $this->orderService->checkout(Yii::$app->user->id, $form);

                return $this->redirect(['/shop/cart/order-success', 'order_id' => $order->id]);
            } catch (\DomainException $e) {
                Yii::$app->errorHandler->logException($e);
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }

        return $this->render('index', [
            'cart' => $cart,
            'model' => $form,
            'fileModel' => $fileForm,
            'warehouseCurrent' => $this->warehouses->find(LocationHelper::getWarehouseId()),
            'warehouseDefault' => $this->warehouses->findDefault()
        ]);
    }

    public function actionOrderSuccess($order_id)
    {
        return $this->render('order-success', ['orderId' => $order_id]);
    }

    public function actionFileAdd()
    {
        $form = new FileAddToCartForm();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $result = $this->service->addFromFile($form);

            } catch (\DomainException $e){
                Yii::$app->errorHandler->logException($e);
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }
        return $this->redirect(['index']);
    }

    public function actionFastAdd()
    {
        $form = new FastAddToCartForm();

        if ($form->load(Yii::$app->request->post(), '') && $form->validate()) {
            if (!$product = $this->products->findByCode($form->code)) {
                throw new NotFoundHttpException('The requested page does not exist.');
            }

            $addForm = new AddToCartForm($product);

            if ($addForm->load(Yii::$app->request->post(), '') && $addForm->validate()) {
                try {
                    $this->service->add($product->id, null, $form->quantity);
                    return $this->redirect(['index']);
                } catch (\DomainException $e) {
                    Yii::$app->errorHandler->logException($e);
                    Yii::$app->session->setFlash('error', $e->getMessage());
                }

            }
        }
        return $this->redirect(['index']);
    }

    /**
     * @param $id
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionAdd($id)
    {
        if (!$product = $this->products->find($id)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $this->layout = 'blank';


        $form = new AddToCartForm($product);

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                //if product does not have any modifications that will add him to cart and return
                if (!$product->modificationAssignments) {
                    $this->service->add($product->id, null, $form->quantity);
                } else {
                    $this->service->add($product->id, $form->modifications, $form->quantity);
                }

                return $this->redirect(['index']);
            } catch (\DomainException $e) {
                Yii::$app->errorHandler->logException($e);
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }

        return $this->render('add', [
            'product' => $product,
            'model' => $form,
        ]);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function actionQuantity($id)
    {
        try {
            $this->service->set($id, (int)Yii::$app->request->post('quantity'));
            return true;
        } catch (\DomainException $e) {
            Yii::$app->errorHandler->logException($e);
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return false;
//        return $this->redirect(['index']);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function actionRemove($id): bool
    {
        try {
            $this->service->remove($id);
            return true;
        } catch (\DomainException $e) {
            Yii::$app->errorHandler->logException($e);
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return false;
    }


    /**
     * @param $id
     * @param $item_id
     * @return mixed
     */
    public function actionRemoveModification($id, $item_id)
    {
        try {
            $this->service->removeModification($id, $item_id);
            return true;
        } catch (\DomainException $e) {
            Yii::$app->errorHandler->logException($e);
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
        return false;
//        return $this->redirect(['index']);
    }

}