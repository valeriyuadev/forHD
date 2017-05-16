<?php
namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;

use app\models\LoginForm;
use app\models\ContactForm;
use app\models\EntryForm;
use app\models\Country;
use app\models\Detailes;
use app\models\SearchUtils;

use yii\helpers\Html;
use yii\widgets\LinkPager;
use yii\web\Session;


//use yii\data\ActiveDataProvider;
use yii\data\Sort;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;

class SearchController extends Controller
{
    /** @var \app\models\SearchUtils $_SU */
    private $_SU;
    
    /**
     * SearchController constructor.
     * @param string $id
     * @param \yii\base\Module $module
     * @param array $config
     */
    public function __construct($id, $module, $config = [])
    {
        $this->_SU = new SearchUtils();

        $this->_SU->setUrl( \yii\helpers\Url::to( 'search/index' ) );
        
        Yii::info('OK -> Loaded', __METHOD__);
        
        parent::__construct($id, $module, $config);
    }
    
    /**
     * Homepage
     * @return string
     */
    public function actionIndex()
    {
	    $perpage      = $this->_SU->_getPagerDropBoxData();
	    $sorter       = $this->_SU->_getSorterDropBoxData();
        	
        return $this->render('index', [ 
            'listDataProvider' => $this->_SU->_getDataProvider( $perpage[ 0 ] ),
            'sorter'           => $sorter[0],
            'sorter_title'     => $sorter[1],
            'perpage'          => $perpage,
            'session'          => Yii::$app->session,
        ]);
    }
        
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [ // Настройка прав доступа пользователей
                'class' => AccessControl::className(),
                'only'  => [ 'index'],
                'rules' => [
                    [
                        'allow' => true, // Могут выполнять действия
                        //'roles' => ['@'], // Только авторизованные пользователи
                        'verbs' => ['POST', 'GET'] // Если данные приходят через POST или GET методы
                    ],
                    [
                        'allow' => false, // Для всех остальных запрещено выполнять данные действия
                    ],
                ],
            ],
        ];
    }

    /**
     * someday add captcha
     * @return array
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**Olded*/
    public function actionAjaxGetMarks( $idBrands ) {
        Yii::$app->response->format = \yii\web\Response::FORMAT_HTML;

        $idBrands = array_values( json_decode( urldecode( $idBrands ) ) );

        if( ! is_array( $idBrands ) ) {
            return '';
        }

        $brands = \app\models\Brands::find()
            //->where( 'id IN (' . implode( ', ', $idBrands ) . ')' )
            ->where( $idBrands ) // thanks yii :)
            ->asArray()
            ->orderBy( ['name' => SORT_ASC ]  )
            ->all() ;

        $brands_names = ArrayHelper::map( $brands, 'id', 'name' );

        $render = array();

        foreach( $brands_names as $id => $name ) {
            $marks = \app\models\Marks::find()
                ->where( "fk_brands = {$id}" )
                ->asArray()
                ->all();

            $render[ $name ] = ArrayHelper::map( $marks, 'id', 'name' );
        }

        return $this->renderPartial(
            'ajax_get_marks',
            [
                'marks' => $render
            ]
        );
    }
}
?>