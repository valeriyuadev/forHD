<?php

namespace app\controllers;

use app\models\Orders;
use Yii;
use app\models\Brands;
use app\models\Detailes;
use app\models\BaseModel;
use app\models\Cart;
use yii\web\Controller;
use yii\web\Session;

use yii\data\ActiveDataProvider;

use yii\filters\AccessControl;
use yii\filters\VerbFilter;

use yii\web\NotFoundHttpException;


/**
 * BrandsController implements the CRUD actions for Brands model.
 */
class CartController extends Controller
{
    private $session;
    
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['add', 'delete', 'items', 'pay'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'add' => ['post'],
                    'del' => ['POST'],                    
                ],
            ],
        ];
    }


    /**
     * when pay, save cart detail to db, send bill to customer and wait
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException
     * @throws \Exception
     */
    public function actionPay() {
        $idUser  = (int)\Yii::$app->user->getId();
        $cartSum = Cart::getCartSum( ['id_user' => $idUser] );
        $hashMd5 = $this->_getHashMd5( $cartSum );
        $hash    = isset($_POST['hash']) ? $_POST['hash'] : false;
        
        if( !$hash || $hash !== $hashMd5 ) {
            return $this->redirect(['/search',]);
        }
        
        // check cart is empty
        if( ! (int)Cart::find()->where( ['id_user' => $idUser ] )->count() ) {
            throw new NotFoundHttpException( \Yii::t( 'cart', 'DO_NOT_DO_THAT' ) );
        }
        
        $sqlNewOrders     = "INSERT INTO orders(id_user, price) VALUES( :id_user, :price )";
        $sqlUpdCartItems  = 'UPDATE cart SET id_order = :id_order, cdate = :cdate WHERE id_user = :id_user';
        $sqlClearCart     = "DELETE FROM cart WHERE  id_order = :id_order AND  id_user = :id_user";
        $sqlNewOrderItem  = "INSERT INTO order_item (id_order, id_user, id_detail, price, detail_descr)
                             SELECT id_order, id_user, id_detail, price, detail_descr
                             FROM cart 
                             WHERE id_user = :id_user";

        $db  = \Yii::$app->db;

        $transaction = $db->beginTransaction();
        
        try {
            // create new order
            $db->createCommand( $sqlNewOrders )
                ->bindParam( ':id_user', $idUser )
                ->bindParam( ':price', $cartSum )
                ->execute();

            if( ! $idOrder = $db->getLastInsertID() ) {
                throw new NotFoundHttpException( \Yii::t( 'cart', 'ERROR_CREATE_ORDER' ) );
            }

            // update cart for current user add to it id_order
            try {
                $cdate = Orders::findOne( $idOrder );

                $db->createCommand( $sqlUpdCartItems )
                    ->bindParam( ':id_order', $idOrder )
                    ->bindParam( ':cdate', $cdate->cdate )
                    ->bindParam( ':id_user', $idUser )
                    ->execute();
            }
            catch( \Exception $e ) {
                throw new NotFoundHttpException( \Yii::t( 'cart', 'ERROR_UPDATE_CART' ) );
            }
            
            // copy all elements of current cart to order_item table
            try {
                $db->createCommand( $sqlNewOrderItem )
                    ->bindParam(':id_user', $id_user )
                    ->execute();
            }
            catch( \Exception $e ) {
                throw new NotFoundHttpException(
                    \Yii::t( 'cart', 'ERROR_MODIFY_ORDER_DETAILE' ) . $sqlNewOrderItem
                );
            }

            // clear cart for current user
            try {
                $sqlClearCart = sprintf( $sqlClearCart, $idOrder, $idUser );
                $db->createCommand( $sqlClearCart )
                    ->bindParam(':id_order', $idOrder )
                    ->bindParam(':id_user', $idUser )
                    ->execute();
            }
            catch( \Exception $e ) {
                throw new NotFoundHttpException( \Yii::t( 'cart', 'ERROR_CLEARE_CART' ) );
            }
            
            \Yii::$app->email->sendNewOrder( $idOrder );
            
            $transaction->commit();
    
        } catch(\Exception $e) {

            $transaction->rollBack();

            throw $e;
        }
        
        return $this->render('pay', [
            'idOrder' => $idOrder,
        ]);
    }

    /** delete item from cart */
    public function actionDelete( $id ) {
        if( $model = Cart::findOne( ['id_detail' => $id ] ) ) {
            $model->delete();
        }
        
        return $this->redirect( '/cart/items' );
    }

    /** add item to cart */
    public function actionAdd( $id ) {
        $this->isAjax();
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $id_detail = (int)$id;
        $message   = ':-) error - exist detail in cart';
        $error     = 1;
        $cartSum   = 0;
        $cartCount = 0;
        $ssid      = Cart::getSessionId();

        $cartExists = Cart::findOne( [ 'ssid' => $ssid, 'id_detail' => $id_detail ] );

        if( $cartExists ) {
            $message = 'Error - how you did it';
        }
        else {
            $model = new Cart();

            $model->id_detail = $id_detail;
            $model->id_user = \Yii::$app->user->getId();
            $model->detail_descr = Detailes::getDescription($id_detail);
            $model->ssid = $ssid;
            $model->price = Detailes::findOne($id_detail)->price;

            if (!$model->validate()) {
                $message = 'Error - model validation';
            } else {
                if (!$model->save()) {
                    $message = 'Error - model save';
                } else {
                    $message = ':-) - add to cart';
                    $error = 0;
                    $cartSum = Cart::getCartSum(
                        ['id_user' => (int)$model->id_user],
                        true
                    );

                    $cartCount = $cartSum[1];
                    $cartSum = $cartSum[0];
                }
            }
        }

        $htmlCart = $this->renderPartial('cart_html');

        return [
            'success'  => true,
            'id'       => $id_detail,
            'error'    => $error,
            'message'  => $message,
            'sum'      => $cartSum,
            'count'    => $cartCount,
            'htmlCart' => $htmlCart,
        ];
    }

    /** list cart items */
    public function actionItems() {
        $where      = [ 'id_user' => \Yii::$app->user->getId() ];
        $cartSum    = Cart::getCartSum( $where );
        $perPage    = \Yii::$app->params['perPageInCart'];   // rename parameter

        if( \Yii::$app->user->isGuest || ! $cartSum ) {
            return $this->redirect( ['/'] );
        }

        return $this->render('items', [
            'listDataProvider' => $this->_getDataProvider( $where, $perPage ),
            'hash'             => $this->_getHashMd5( $cartSum ),
            'cartSum'          => $cartSum,
        ]);
    }

    /**
     * @param $cartSum
     * @return string
     */
    private function _getHashMd5($cartSum ) {
        return md5($cartSum . \Yii::$app->params['md5_hash_Valera']);
    }

    /**
     * @param $where
     * @param $perpage
     * @return ActiveDataProvider
     */
    private function _getDataProvider($where, $perpage ) {
        $dataProvider = new ActiveDataProvider([
            'query' => Cart::find()->with('detail')->where( $where ),
            'sort'  => [
                'defaultOrder' => [
                    'id'        => SORT_ASC,
                ],
            ],
            'pagination' => [
                'pageSize' => $perpage,
            ]
        ]);

        return $dataProvider;
    }

    /**
     * add item to cart - only ajax
     * @throws NotFoundHttpException
     */
    private function isAjax() {
        if( !Yii::$app->request->isAjax ) {
            throw new NotFoundHttpException('Only Ajax.'); 
        }
    }

    /**
     * Finds the Brands model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Brands the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findDetailesModel($id)
    {
        if (null !== ($model = \app\models\Detailes::findOne($id))) {
            return $model;
        } else {
            throw new NotFoundHttpException('Does not exist');
        }
    }
}
