<?php


namespace Tan\ERP\Entities;

/**
 * {@inheritdoc}
 *
 * @property string $firstName
 * @property string $lastName
 * @property string $phone
 * @property string $email
 * @property string $description
 * @property string $company
 * @property bool $optIn
 * @property bool $optInLetter
 * @property bool $optInSms
 * @property bool $optInPhone
 * @property bool $deliveryAddress
 * @property bool $invoiceAddress
 * @property bool $primeAddress
 * @property string $personCompany name of linked company for the contact
 * @property Address[] $addresses RESERVED
 */
abstract class Contact extends BaseEntity
{
    const ENTITY_NAME = 'contact';
    const PARTY_TYPE_PERSON = 'PERSON';

    /**
     * default party type
     */
    const PARTY_TYPE = self::PARTY_TYPE_PERSON;

    protected $attributes = [
        'version' => 0,
        'optIn' => false,
        'optInLetter' => false,
        'optInSms' => false,
        'optInPhone' => false,
        'partyType' => self::PARTY_TYPE_PERSON,
    ];
    protected $casts = [
        'optIn' => 'boolean',
        'optInLetter' => 'boolean',
        'optInSms' => 'boolean',
        'optInPhone' => 'boolean',
        'lastModifiedDate' => 'datetime:Uv',
        'createdDate' => 'datetime:Uv',
        'addresses' => 'array',
    ];


    /**
     * {@inheritdoc}
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes['partyType'] = static::PARTY_TYPE;
        parent::__construct($attributes);
    }
}
