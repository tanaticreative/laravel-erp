<?php


namespace Tan\ERP;

use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Support\Facade;
use Illuminate\Support\Collection;

class SimpleQuery
{
    protected $entity;
    protected $entityClass;

    protected $page = 1;
    protected $pageSize = 100;
    protected $withNulls = true;
    protected $where = [];
    protected $sorting = [];


    public function __construct(BaseEntity $entity)
    {
        $this->entity = $entity;
        $this->entityClass = get_class($this->entity);
    }


    /**
     * @throws Exceptions\ApiNotFoundException
     * @throws Exceptions\ApiOptimisticLockException
     * @return array
     */
    public function update()
    {
        return Facade::getClient()->update($this->entity);
    }


    /**
     * @return array
     */
    public function create()
    {
        return Facade::getClient()->create($this->entity);
    }


    /**
     * @throws \Exception
     */
    public function delete()
    {
        Facade::getClient()->delete($this->entity);
    }


    /**
     * @param boolean $withNulls
     * @return $this
     */
    public function withNulls($withNulls)
    {
        $this->withNulls = (bool)$withNulls;
        return $this;
    }


    /**
     * @param $page
     * @return $this
     */
    public function page($page)
    {
        $this->page = abs($page);
        return $this;
    }


    /**
     * @param $pageSize
     * @return $this
     */
    public function pageSize($pageSize)
    {
        $this->pageSize = abs($pageSize);
        return $this;
    }


    /**
     * @param $column
     * @param string $operator
     * @param mixed $value
     * @param string $boolean [RESERVED] Default is 'and'
     * @return $this
     */
    public function where($column, $operator, $value, $boolean = 'and')
    {
        $this->where[] = ['col' => $column, 'oper' => $operator, 'val' => $value];
        return $this;
    }


    /**
     * @param array $sorting [ fieldName => ASC|DESC, ..  ]
     * @return $this
     */
    public function sorting(array $sorting = [])
    {
        $this->sorting = $sorting;
        return $this;
    }


    /**
     * Find entity by ID
     *
     * @param int $id
     * @return BaseEntity|null
     */
    public function find($id)
    {
        $data = Facade::getClient()->find($this->entityClass, $id);
        $entity = $data ? new $this->entityClass($data) : null;

        return $entity;
    }


    /**
     * @return int
     */
    public function count()
    {
        return Facade::getClient()->count($this->entityClass, $this->where, $this->withNulls);
    }


    /**
     * @return Collection|BaseEntity[]
     */
    public function get()
    {
        $result = Facade::getClient()->query($this->entityClass, $this->sorting, $this->where, $this->withNulls, $this->page, $this->pageSize);
        $items = [];
        foreach ($result as $data) {
            $items[] = new $this->entityClass($data);
        }

        return Collection::make($items);
    }
}
