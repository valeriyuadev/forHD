<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "detailes".
 *
 * @property integer $id
 * @property integer $fk_brands
 * @property integer $fk_marks
 * @property integer $fk_categories
 * @property string $cdate
 * @property string $who
 *
 * @property Marks $fkMarks
 * @property Brands $fkBrands
 * @property Modifications $fk_modifications
 * @property Photos[] $photos
 */
class Detailes extends BaseModel //\yii\db\ActiveRecord
{
    const SOLD_NO  = 0;
    const SOLD_YES = 1;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'detailes';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['fk_brands', 'fk_marks', 'fk_categories', 'price'], 'required'],
            [['id', 'fk_brands', 'fk_marks', 'fk_categories', 'year_model', 'sold', 'price'], 'integer'],
            [['cdate', 'year_model', 'modification_name', 'fk_modifications', 'sold', 'price'], 'safe'],
            [['who'], 'string', 'max' => 5],
	         [['modification_name'], 'string', 'max' => 20],
            [['fk_marks'], 'exist', 'skipOnError' => true, 'targetClass' => Marks::className(), 'targetAttribute' => ['fk_marks' => 'id']],
            [['fk_brands'], 'exist', 'skipOnError' => true, 'targetClass' => Brands::className(), 'targetAttribute' => ['fk_brands' => 'id']],
            //[['fk_modifications'], 'exist', 'skipOnError' => true, 'targetClass' => Modifications::className(), 'targetAttribute' => ['fk_modifications' => 'id']],
            [['fk_modifications'], 'exist', 'skipOnError' => true, 'targetClass' => Modifications::className(), 'targetAttribute' => ['fk_modifications' => 'id']],
            //[['fk_modifications'], 'default', 'value' => null],
            [['fk_modifications'], 'integer', 'integerOnly' => false],
            //[['fk_modifications'], 'integer'],
            [['fk_modifications'], 'default', 'value'=>null],
            
            [['price'], 'default', 'value'=>1],
            [['price'], 'compare', 'compareValue' => 0, 'operator' => '>'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'                => Yii::t('app', 'Article'),
            'fk_brands'         => Yii::t('app', 'Brands'),
            'fk_marks'          => Yii::t('app', 'Marks'),
            'fk_categories'     => Yii::t('app', 'Category'),
            
            'fk_modifications'  => Yii::t('app', 'Type'),
            'modification_name' => Yii::t('app', 'Model'),
            'year_model'        => Yii::t('app', 'Year'),
            
            'sold'              => Yii::t('app', 'Avaible'),
            
            'cdate'             => Yii::t('app', 'Cdate'),
            'who'               => Yii::t('app', 'Who'),
            
            'price'             => Yii::t('app', 'Price'),
            
            'priceEquality'     => Yii::t('app', 'Price Equality'),
        ];
    }

    /**
     * @param int $idDetail
     * @return string
     */
    public static function getDescription( $idDetail ) {
        $model = self::findOne(['id'=>(int)$idDetail]);

        $mod_name = $model->modification_name ? ' ' . $model->modification_name : '';

        $category = BaseModel::_tr2( 'categories', $model->fkCategories->name );

        $mod_type = $model->fk_modifications
            ? ', ' . BaseModel::_tr2( 'modifications', $model->fkModifications->name )
            : '';

        $detail_descr =
            $model->fkBrands->name
            . ' '
            . $model->fkMarks->name
            . $mod_name
            . ', '
            . $category
            . $mod_type;

        return $detail_descr;
    }

    // custom field
    public function getPriceEquality() {
        return [
            '='  => '=',
            '>'  => '>',
            '<'  => '<',
            '<=' => '<=',
            '>=' => '>=',
            '!=' => '!=',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     * */
    public function getFkModifications() 
    {
        return $this->hasOne( Modifications::className(), ['id' => 'fk_modifications'] );
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFkMarks()
    {
        return $this->hasOne(Marks::className(), ['id' => 'fk_marks']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFkBrands()
    {
        return $this->hasOne(Brands::className(), ['id' => 'fk_brands']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFkCategories()
    {
        return $this->hasOne(Categories::className(), ['id' => 'fk_categories']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrderItem()
    {
        return $this->hasMany(OrderItem::className(), ['id_detail' => 'id']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPhotos()
    {
        return $this->hasMany(Photos::className(), ['fk_detailes' => 'id']);
    }
    
    public function getCarts()
    {
        return $this->hasMany(Cart::className(), ['id_detail' => 'id']);
    }
    
    /** Return sold data, or sold avaible status 
     * @param integer $key optional - sold status
     * @return mixed array|string 
     */
    public function getSoldData( $key = -1 ) {
        $key = (int)$key;

        // Translate it
        $data = [
            self::SOLD_YES => 'Yes',
            self::SOLD_NO  => 'Not'
        ];
        
        foreach( $data as $k => $v ) {
           $data[ $k ] = \app\models\BaseModel::_tr2( 'app', $v );     
        }
        
        return -1 == $key ? $data : $data[(int)$key];
    }
}