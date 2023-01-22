<?php


namespace Tan\ERP\Entities;

/**
 * Article ERP Entity
 *
 * @property string $name
 * @property string $longText
 * @property string $articleNumber
 * @property bool $active
 * @property int $articleCategoryId
 * @property string $amazonASIN
 * @property string $amazonFBASKU
 * @property string $amazonSKU
 * @property string $articleGrossWeight
 * @property string $articleHeight
 * @property bool applyCashDiscount
 * @property int $unitId
 * @property string $unitName
 * @property bool $serviceArticle
 * @property bool $productionArticle
 */
abstract class Article extends BaseEntity
{
    const ENTITY_NAME = 'article';
    const ARTICLE_TYPE = self::ARTICLE_TYPE_BASIC;

    const ARTICLE_TYPE_BASIC = 'BASIC';
    const ARTICLE_TYPE_SERVICE = 'SERVICE';

    protected $casts = [
        'active' => 'boolean',
        'applyCashDiscount' => 'boolean',
        'serviceArticle' => 'boolean',
        'productionArticle' => 'boolean',
        'unitId' => 'integer',
    ];
}
