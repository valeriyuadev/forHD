<?php
namespace app\models;

use yii\data\ActiveDataProvider;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\widgets\LinkPager;
use yii\web\Session;

use app\models\Detailes;
use app\models\DetailesSearch;
use app\models\Brands;
use app\models\Marks;
use app\models\Categories;
use app\models\Modifications;

/**
 * Class DetailUtils
 * @package app\models
 */
class DetailUtils
{
    /**
     * @var array
     */
    private $_ajax_res = [
        'data'    => null,
        'error'   => null,
        'success' => false,
    ];

    /**
     * @param int $idBrand
     * @return array
     */
    public function ajaxGetMarks($idBrand) {
        $idBrand = (int)$idBrand;

        if ( ! Yii::$app->request->isAjax ) {
            throw new NotFoundHttpException('The requested page - only AJAX.');
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if( !$idBrand ) {
            $this->_ajax_res['error'] = "Brand ID invalid - '{$idBrand}'...";
        }
        else {
            $data = Marks::getMarksByBrandId( $idBrand );

            $this->_ajax_res['data']    = $data;
            $this->_ajax_res['success'] = true;
        }

        return $this->_ajax_res;
    }

    /**
     * @param int $idCategory
     * @return array
     */
    public function ajaxGetModifications($idCategory) {
        $idCategory = (int)$idCategory;

        if ( ! Yii::$app->request->isAjax ) {
            throw new NotFoundHttpException('The requested page - only AJAX.');
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if( ! $idCategory ) {
            $this->_ajax_res['error'] = "Category ID invalid - '{$idCategory}'...";
        }
        else {
            $data = \app\models\Modifications::getModificationsByCategoryId( $idCategory );

            $this->_ajax_res['data']    = $data;
            $this->_ajax_res['success'] = true;
        }

        return $this->_ajax_res;
    }
}