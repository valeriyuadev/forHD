<?php
namespace app\models;

use yii\data\ActiveDataProvider;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\widgets\LinkPager;
use yii\web\Session;

class SearchUtils //extends \yii\base\Object
{
    /** @var string $_url */
    private $_url;

    /**
     * @param string $url
     */
    public function setUrl($url ) {
        $this->_url = $url;
    }

    /** Make sql from serach query string
     * @return ActiveQuery
     */
    public function _getSqlFromGet( $detail = false ) {
        $QS = $this->_varQueryString();

        $brands   = isset( $QS['brands'] ) ? (array)$QS['brands'] : false;
        $marks    = isset( $QS['marks'] ) ? (array)$QS['marks'] : false;
        $category = isset( $QS['category'] ) ? (array)$QS['category'] : false;

        $modification       = isset( $QS['modification'] ) ? (array)$QS['modification'] : false;
        $modifications_name = isset( $QS['modifications_name'] ) ? Html::encode( trim( $QS['modifications_name'] ) ) : '';

        $price_min = isset( $QS['price_min'] ) ? (float)$QS['price_min'] : false;
        $price_max = isset( $QS['price_max'] ) ? (float)$QS['price_max'] : false;

        $detail = \app\models\Detailes::find()
            ->select(
                [
                    'detailes.*',
                    'c.id_detail',
                    /*'order_item.id_detail',  // зачем так сделал...?*/
                ])
            ->joinWith([ 'fkBrands' ])
            ->joinWith([ 'carts c' ])
            //->joinWith([ 'orderItem' ])
            ->where( 'sold != 1' );

        $detail->andWhere( ' c.id_detail IS NULL ' );

        if( ! \Yii::$app->user->isGuest ) {
            $detail->andWhere(' 
                detailes.id NOT IN (
                    SELECT id_detail
                    FROM order_item oi,
                         `detailes` d
                    WHERE 
                        oi.`id_detail` = d.`id`
                        AND d.sold  = 1
                        AND id_user = '. \Yii::$app->user->identity->getId() .'
                )
            ');
        }

        if( $brands ) {
            $sql   = '';
            $param = array();

            foreach( $brands as $k => $id ) {
                $sql .= $sql != '' ? ' OR ' : '';

                $name         =  ':fk_brands_' . $id;
                $param[$name] =  $id;
                $sql          .= 'detailes.fk_brands = ' . $name;
            }

            $detail->andWhere( $sql, $param );
        }

        if( $marks ) {
            $sql   = '';
            $param = array();

            foreach( $marks as $k => $id ) {
                $sql .= $sql != '' ? ' OR ' : '';

                $name         =  ':marks_' . $id;
                $param[$name] =  $id;
                $sql          .= 'fk_marks = ' . $name;
            }

            $detail->andWhere( $sql, $param );
        }

        if( $modifications_name ) {
            $detail->andWhere(
                'LOWER( modification_name ) LIKE :modifications_name',
                [ ':modifications_name' => '%'.$modifications_name.'%' ]
            );
        }

        if( $price_min || $price_max ) {
            if( $price_min <= 0 ) {
                $_GET[ 'price_min' ] = null;
                $price_min = false;
            }

            if( $price_max <= 0 ) {
                $_GET[ 'price_max' ] = null;
                $price_max = false;
            }

            if( $price_min && $price_max ) {
                if( $price_max < $price_min ) {
                    parse_str( $_SERVER['QUERY_STRING'], $query_string );

                    $query_string['price_min'] = $price_max;
                    $query_string['price_max'] = $price_min;

                    $_GET['price_min']       = $price_max;
                    $_GET['price_max']       = $price_min;
                    $_SERVER['QUERY_STRING'] = http_build_query($query_string);
                }

                $min = $price_min < $price_max ? $price_min : $price_max;
                $max = $price_min > $price_max ? $price_min : $price_max;

                $detail->andWhere(
                    '( detailes.price >= :price_min AND detailes.price <= :price_max )',
                    [':price_min' => $price_min, ':price_max' => $price_max ]
                );
            }
            else if( $price_min ) {
                $detail->andWhere( 'detailes.price >= :price_min', [ ':price_min' => $price_min ] );
            }
            else if( $price_max ) {
                $detail->andWhere( 'detailes.price <= :price_max', [ ':price_max' => $price_max ] );
            }
        }

        /////////// !!! one sql
        $sql   = '';
        $param = array();

        if( $category ) {
            foreach( $category as $k => $id ) {
                $name         =  ':fk_categories_' . $id;
                $param[$name] =  $id;

                $sql .= $sql !=  '' ? ' OR ' : '';
                $sql .= 'fk_categories = ' . $name;
            }
        }

        if( $modification ) {
            foreach( $modification as $k => $mod ) {
                foreach( $mod as $id ) {
                    $name         =  ':fk_modifications_' . $id;
                    $param[$name] =  $id;

                    $sql .= $sql != '' ? ' OR ' : '';
                    $sql .= 'fk_modifications = ' . $name;
                }
            }
        }

        if( $sql != '' ) {
            $detail->andWhere( $sql, $param );
        }

        if( ! \Yii::$app->request->get( 'sort' ) ) {
            $detail->orderBy( ['brands.name' => SORT_ASC, ] );
        }

        return $detail;
    }

    /**
     * @return array parsed vars
     */
    public function _varQueryString() {
        parse_str( \Yii::$app->request->getQueryString(), $QS );

        return $QS;
    }



    /**Make data for sort field dropbox
     * @return array
     */
    public function _getSorterDropBoxData() {
        $sort    = \Yii::$app->request->get( 'sort' );
        $sort    = ! $sort ? 'brand' : $sort;

        $minus   = false === strpos( trim($sort), '-' ) ? true : false;
        $css     = $minus ? 'asc' : 'desc';
        $sort    = str_replace( '-', '', $sort );
        $sel_fld = 'Sort field';
        $sel_cls = '';
        $fields  = [
            'id'           => 'Article',
            'brand'        => 'Brand',
            'mark'         => 'Mark',
            'category'     => 'Category',
            'modification' => 'Type',
            'price'        => 'Price',
        ];

        $back = array();
        $QS   = $this->_varQueryString();

        if( isset( $QS[ 'sort' ] ) ) {
            unset( $QS[ 'sort' ] );
        }

        $QS = http_build_query( $QS );

        foreach( $fields as $field => $title ) {
            if( $field == $sort ) {
                $class    = $css;
                $sel_fld  = $title;
                $sel_cls  = $css;
                $addminus = $minus ? '-' : '';
            }
            else {
                $class    = '';
                $addminus = '';
            }

            $sort_val = $addminus . $field;

            $back[] = [
                'href'        => Url::to( [ $this->_url , 'sort' => $sort_val ] ) . '&' .$QS
                , 'title'     => $title
                , 'class'     => $class
                , 'data-sort' => $sort_val,
            ];
        }

        //$sel_fld

        return [ $back, [ $sel_fld, $sel_cls ] ];
    }

    /**Make data for per page dropbox
     * @return array
     */
    public function _getPagerDropBoxData() {
        $perpage = (int)( \Yii::$app->request->get( 'perpage' ) );
        $inPage  = \Yii::$app->params['items_per_page'];

        if( ! array_key_exists( $perpage, $inPage )
            || ! (int)$perpage || (int)$perpage > \Yii::$app->params['max_in_page'] )
        {
            $perpage = \Yii::$app->params['min_in_page'];
        }

        $QS   = $this->_varQueryString();

        if( isset( $QS[ 'perpage' ] ) ) {
            unset( $QS[ 'perpage' ] );
        }

        if( isset( $QS[ 'per-page' ] ) ) {
            unset( $QS[ 'per-page' ] );
        }

        $QS = http_build_query( $QS );

        foreach( $inPage as $k => $v ) {
            $inPage[ $k ][ 'href' ] = Url::to( [ $this->_url , 'perpage' => $k ] ) . '&' .$QS;
        }

        return [ $perpage, $inPage ];
    }

    /** get ActiveDataProvider for the ListView
     * @param int $perpage elements on page
     *
     * @return ActiveDataProvider
     */
    public function _getDataProvider( $perpage ) {
        $detail = $this->_getSqlFromGet();

        $dataProvider = new ActiveDataProvider([
            'query' => $detail,
            'sort' => [
                'defaultOrder' => [
                    'id'        => SORT_ASC,
                    //'fk_brands' => SORT_ASC,
                ],
                'attributes' => $this->_getSortingFields(),
            ], // /'sort'
            'pagination' => [
                'pageSize' => $perpage,
            ]
        ]);

        return $dataProvider;
    }

    /** get rules sorting for ActiveDataProvider
     * @return array
     */
    public function _getSortingFields() {
        return   [
            'id'    => [
                'asc'     => ['id' => SORT_ASC],
                'desc'    => ['id' => SORT_DESC],
                'default' => SORT_ASC,
                'Title' => 'Article',
            ],
            'brand' => [
                'asc'     => ['fk_brands' => SORT_ASC,  'fk_brands' => SORT_ASC],
                'desc'    => ['fk_brands' => SORT_DESC, 'fk_brands' => SORT_DESC],
                'default' => SORT_ASC,
                'Title' => 'Brand',
            ],
            'mark' => [
                'asc'     => ['fk_marks' => SORT_ASC,  'fk_marks' => SORT_ASC],
                'desc'    => ['fk_marks' => SORT_DESC, 'fk_marks' => SORT_DESC],
                'default' => SORT_ASC,
                'Title' => 'Brand',
            ],
            'category' => [
                'asc'     => ['fk_categories' => SORT_ASC,  'fk_categories' => SORT_ASC],
                'desc'    => ['fk_categories' => SORT_DESC, 'fk_categories' => SORT_DESC],
                'default' => SORT_ASC,
                'Title' => 'Category',
            ],
            'modification' => [
                'asc'     => ['fk_modifications' => SORT_ASC,  'fk_modifications' => SORT_ASC],
                'desc'    => ['fk_modifications' => SORT_DESC, 'fk_modifications' => SORT_DESC],
                'default' => SORT_ASC,
                'Title' => 'Type',
            ],
            'price' => [
                'asc'     => ['price' => SORT_ASC,  'price' => SORT_ASC],
                'desc'    => ['price' => SORT_DESC, 'price' => SORT_DESC],
                'default' => SORT_ASC,
                'Title' => 'Price',
            ],
        ];
    }
}