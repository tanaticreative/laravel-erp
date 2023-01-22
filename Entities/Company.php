<?php


namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Models\SyncState;
use Illuminate\Support\Facades\DB;

/**
 * Company ERP Entity
 *
 * @property string $annualRevenue
 * @property string $partyType
 * @property string $company
 * @property string $description
 * @property bool $optIn
 * @property bool $optInLetter
 * @property bool $optInSms
 * @property bool $optInPhone
 * @property int $customerCategoryId
 * @property string $customerCategoryName
 * @property Address[] $addresses
 * @property Contact[] $contacts
 *
 * @property \App\Models\Company $model
 */
abstract class Company extends BaseEntity
{
    const PARTY_ORGANIZATION = 'ORGANIZATION';

    protected $attributes = [
        'partyType' => self::PARTY_ORGANIZATION,
        'optIn' => false,
        'optInLetter' => false,
        'optInSms' => false,
        'optInPhone' => false,
    ];
    protected $casts = [
        'optIn' => 'boolean',
        'optInLetter' => 'boolean',
        'optInSms' => 'boolean',
        'optInPhone' => 'boolean',
        'lastModifiedDate' => 'datetime:Uv',
        'createdDate' => 'datetime:Uv',
        'customerCategoryId' => 'integer',
        //'addresses' => 'array',
        //  'contacts' => 'array',
    ];


    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        throw new NotSupportedByAGRException('Not implemented yet and never will');
    }


    public function getAddressesAttribute()
    {
        return $this->attributes['addresses'] ?? [];
    }


    public function setAddressesAttribute($addresses)
    {
        if (!is_array($addresses)) {
            throw new \InvalidArgumentException("MUST BE ARRAY");
        }

        foreach ($addresses as &$address) {
            if ($address instanceof Address) {
                $arr[] = $address;
                continue;
            }
            if (is_array($address)) {
                $address = new Address($address);
                continue;
            }
            throw new \InvalidArgumentException("MUST BE INSTANCE OF " . Address::class . " or array!");
        }


        $this->attributes['addresses'] = $addresses;
    }


    public function getContactsAttribute()
    {
        return $this->attributes['contacts'] ?? [];
    }


    public function setContactsAttribute($contacts)
    {
        if (!is_array($contacts)) {
            throw new \InvalidArgumentException("MUST BE ARRAY");
        }

        foreach ($contacts as &$contact) {
            if ($contact instanceof UserContact) {
                continue;
            }
            if (is_array($contact)) {
                $contact = new UserContact($contact);
                continue;
            }
            throw new \InvalidArgumentException("MUST BE INSTANCE OF " . UserContact::class . " or array!");
        }

        // WORKAROUND: ERP bug workaround
        /** @var Contact $contact */
        foreach ($contacts as &$contact) {
            $contact->personCompany = $this->company;
        }

        $this->attributes['contacts'] = $contacts;
    }


    /**
     * {@inheritdoc}
     */
    public function syncModel()
    {
        $model = $this->model;

        DB::transaction(function () use ($model) {
            $model->name = $this->company;
            $model->save();

            if ($this->addresses) {
                $address = $this->addresses[0];
                $address->model = $model->address;
                $address->syncModel();
            }

            $syncState = $model->syncState ?? new SyncState();
            $syncState->entity_id = $this->id;
            $syncState->version = $this->version;
            $syncState->entity_type_id = get_called_class();
            $syncState->target()->associate($model);
            $syncState->save();
        });
    }


    /**
     * {@inheritdoc}
     */
    public function fillFromModel(EntitySyncState $model)
    {
        if (!($model instanceof \App\Models\Company)) {
            throw new \InvalidArgumentException("Only instance of " . \App\Models\Company::class . ' is supported');
        }

        parent::fillFromModel($model);

        $this->company = $model->name;

        if ($model->syncState && $model->syncState->entity_id) {
            $this->id = $model->syncState->entity_id;
        }

        if ($category = CustomerCategory::findByCompanyModel($model)) {
            $this->customerCategoryId = $category->id;
            $this->customerCategoryName = $category->name;
        }

        $goods = CustomerTopics::findByCompanyModel($model);

        if (!empty($goods)) {
            $this->leadTopics = $goods;
        }

        if ($this->addresses) {
            $address = $this->addresses[0];
        } else {
            $address = new Address();
            $this->addresses = [$address];
        }

        $address->fillFromModel($model->address);
    }
}
