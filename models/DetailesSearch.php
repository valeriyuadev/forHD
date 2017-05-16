<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Detailes;

/**
 * DetailesSearch represents the model behind the search form about `app\models\Detailes`.
 */
class DetailesSearch extends Detailes
{
    // custom fileds
    public $priceEquality;
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'fk_brands', 'fk_marks', 'fk_categories', 'fk_modifications', 'year_model', 'sold', 'price'], 'integer'],
            [['cdate', 'who', 'year_model', 'modification_name', 'price'], 'safe'],
            
            [['priceEquality'], 'safe'],
            
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Detailes::find()->with('fkMarks', 'fkBrands', 'fkCategories');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
               'pagesize' => Yii::$app->params['adminElementsPerPage'],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider; // $query->where('0=1');
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id'            => $this->id,
            'fk_brands'     => $this->fk_brands,
            'fk_marks'      => $this->fk_marks,
            'fk_categories' => $this->fk_categories,
            'year_model'    => $this->year_model,
	        'cdate'         => $this->cdate,
            'sold'          => $this->sold,
            'fk_modifications'  => $this->fk_modifications,
        ]);
        
        // Filter Price + Price equality
        if( $this->price ) {
            $operator      = '=';
            $validEquality = $this->getPriceEquality();
            
            if( $this->priceEquality && array_key_exists( $this->priceEquality, $validEquality ) ) {
               $operator = $this->priceEquality;     
            }
            
            $query->andWhere( 'price ' . $operator . ' ' . $this->price );
            $query->andWhere( ' 1 = 1 ' );
        }

        $query->andFilterWhere(['like', 'modification_name', $this->modification_name]);
        $query->andFilterWhere(['like', 'who', $this->who]);

        return $dataProvider;
    }
}
