<?php

namespace App\Repositories;

use App\Models\PointLog;
use Illuminate\Http\Request;

use Illuminate\Database\Eloquent\Model;

class PointRepository
{
    use Traits\Filterable;
    protected $point;

    public function __construct(PointLog $pointLog)
    {
        $this->point = $pointLog;
    }

    /**
     * @param $request
     * @return array
     * 带分页
     */
    public function getPointList($request)
    {
        $builder = ($this->point instanceof Model) ? $this->point->query() : $this->point;
        $sort = explode('-', $request->sort);
        $limit = $request->query('limit', 20);
        $filters = $request->query('filters', '');
        if ($filters && $filters !== null) {
            $maps = $this->formatFilter($filters);
            foreach ($maps['maps'] as $k => $map) {
                $curKey = $maps['fields'][$k];
                $builder->when($curKey, $map[$curKey]);
            }
        }
        $builder->when(($sort && !$sort), function ($query) use ($sort) {
            $query->orderBy($sort[0], $sort[1]);
        });
        $items = $builder->paginate($limit);
        return array(
            'data' => $items->items(),
            'total' => $items->count(),
            'page' => $items->currentPage(),
            'pagesize' => $limit,
            'totalpage' => $items->total(),
        );
    }

    /**
     * @param $request
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     * 无分页
     */
    public function getPointData($request)
    {
        $builder = ($this->point instanceof Model) ? $this->point->query() : $this->point;
        $sort = explode('-', $request->sort);
        $filters = $request->query('filters', '');
        if ($filters && $filters !== null) {
            $maps = $this->formatFilter($filters);
            foreach ($maps['maps'] as $k => $map) {
                $curKey = $maps['fields'][$k];
                $builder->when($curKey, $map[$curKey]);
            }
        }
        $builder->when(($sort && !$sort), function ($query) use ($sort) {
            $query->orderBy($sort[0], $sort[1]);
        });
        $items = $builder->get();
        return $items;
    }
}