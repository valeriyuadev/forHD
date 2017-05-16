<?php

namespace app\controllers;

use Yii;
use app\models\Detailes;
use app\models\DetailesSearch;
use app\models\Brands;
use app\models\Marks;
use app\models\Categories;
use app\models\Modifications;
use app\models\DetailUtils;

use yii\web\Controller;
use yii\web\NotFoundHttpException;

use yii\filters\AccessControl;
use yii\filters\VerbFilter;


/**
 * Class DetailesController
 * @package app\controllers
 */
class DetailesController extends Controller
{
    /** @var array */
    private $_ajax_res = [
        'data'    => null,
        'error'   => null,
        'success' => false,
    ];

    /** @var \app\models\DetailUtils $_DU */
    private $_DU;

    /**
     * DetailesController constructor.
     * @param string $id
     * @param \yii\base\Module $module
     * @param array $config
     */
    public function __construct($id, $module, $config = [])
    {
        $this->_DU = new DetailUtils();

        parent::__construct($id, $module, $config);
    }
    
	/**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['index', 'create', 'update', 'delete'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['adminOnlyRead'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }



    /**
     * Lists all Detailes models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new DetailesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [ 
            'searchModel'   => $searchModel,
            'dataProvider'  => $dataProvider,
            'marks'         => Marks::getMarksByBrandId(),
            'brands'        => Brands::getBrends(),
            'categories'    => Categories::getCategories(),
            'modifications' => Modifications::getModificationsByCategoryId(),
            'sold_satus'    => Detailes::soldData(),
        ]);
    }

    /**
     * Displays a single Detailes model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Detailes model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $maxId = Detailes::find()->select('max(id) + 1 as id')->one();
        $model = new Detailes();
        
        $model->load(Yii::$app->request->post());
        
        if ( $model->validate() && $model->save() ) {
            Yii::$app->session->setFlash('detailCreated');
            
            return $this->redirect(['update', 'id' => $model->id]);
        } else {
            return $this->render('create', [
                'model' => $model,
                'maxId' => $maxId->id,
            ]);
        }
    }

    /**
     * Updates an existing Detailes model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ( $model->load(Yii::$app->request->post()) && $model->save() ) {
            return $this->render('update', [
                'model' => $model,
            ]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Detailes model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $detail = $this->findModel($id);
        $photos = $detail->photos;

        if( $photos ) {
            $dir    = Yii::getAlias( '@webroot/uploads/' ) . $id . '/';

            foreach( $photos as $p ) {
                if( is_file( $dir . $p->img_org ) ) {
                    unlink( $dir . $p->img_org );
                }
                
                if( is_file( $dir . $p->img_thumb ) ) {
                    unlink( $dir . $p->img_thumb );
                }
            }
            
            @rmdir( $dir );            
        }
        
        $detail->delete();

        return $this->redirect(['index']);
    }

    /**
     * get marks collection
     * @param int $idBrand
     * @return array
     */
    public function actionAjaxGetMarks($idBrand) {
        return $this->_DU->ajaxGetMarks( (int)$idBrand );
    }

    /**
     * get modifications collection
     * @param int $idCategory
     * @return array
     */
    public function actionAjaxGetModifications($idCategory) {
        return $this->_DU->ajaxGetModifications( (int)$idCategory );
    }
	
    /**
     * Finds the Detailes model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Detailes the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Detailes::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
    
    
}
