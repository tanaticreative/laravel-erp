<?php


namespace Tan\ERP\Entities\Sales;

use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Exceptions\NotSupportedByERPException;

/**
 * SalesInvoice item ERP entity
 *
 * {@inheritdoc}
 *
 * @property bool $addPageBreakBefore
 * @property string $articleId
 * @property string $articleNumber
 * @property string $discountPercentage
 * @property bool $freeTextItem
 * @property string $grossAmount
 * @property string $grossAmountInCompanyCurrency
 * @property string $groupName
 * @property bool $manualUnitCost
 * @property bool $manualUnitPrice
 * @property string $netAmount
 * @property string $netAmountInCompanyCurrency
 * @property string $note
 * @property string $parentItemId
 * @property int $positionNumber
 * @property string $quantity TODO: field of interest
 * @property array $reductionAdditionItems TODO: add support if needed
 * @property string $taxId
 * @property string $taxName
 * @property string $title
 * @property string $unitCost
 * @property string $unitCostInCompanyCurrency
 * @property string $unitId
 * @property string $unitName
 * @property string $unitPrice
 * @property string $unitPriceInCompanyCurrency
 */
class InvoiceItem extends BaseEntity
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
