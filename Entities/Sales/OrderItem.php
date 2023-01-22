<?php


namespace App\Components\ERP\Entities\Sales;


use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Exceptions\NotSupportedByERPException;

class OrderItem extends BaseEntity
{
    protected $casts = [
        'addPageBreakBefore' => 'boolean',
        'freeTextItem' => 'boolean',
        'positionNumber' => 'integer',
        'manualUnitCost' => 'boolean',
        'manualUnitPrice' => 'boolean',
    ];

    /**
     * {@inheritdoc}
     */
    public static function all()
    {
        throw new NotSupportedByERPException();
    }


    /**
     * {@inheritdoc}
     */
    public function save(array $options = [])
    {
        throw new NotSupportedByERPException();
    }


    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        throw new NotSupportedByERPException();
    }


    /**
     * {@inheritdoc}
     */
    public function syncModel()
    {
        throw new NotSupportedByERPException();
    }
}
