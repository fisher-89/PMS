<?php

namespace App\Http\Controllers\APIs;

use Carbon\Carbon;
use Illuminate\Http\Request;
use function App\monthBetween;
use function App\stageBetween;
use App\Models\PointLog as PointLogModel;
use App\Models\PersonalPointStatistic as StatisticModel;
use App\Models\PersonalPointStatisticLog as StatisticLogModel;

class StaffPointController extends Controller
{
    /**
     * 积分分类统计列表.
     *
     * @author 28youth
     * @param  \Illuminate\Http\Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $datetime = $request->query('datetime');
        // 当前月统计
        if (Carbon::parse($datetime)->isCurrentMonth()) {
            $monthly = StatisticModel::query()
                ->where('staff_sn', $user->staff_sn)
                ->orderBy('calculated_at', 'desc')
                ->first();
        } else {
            $monthly = StatisticLogModel::query()
                ->where('staff_sn', $user->staff_sn)
                ->whereBetween('date', monthBetween($datetime))
                ->first();
        }

        // 前4个月积分趋势数据
        $monthly->trend = $this->statistics();

        return response()->json($monthly, 200);
    }

    /**
     * 获取某一段时间统计结果.
     * 
     * @author 28youth
     * @return mixed
     */
    public function statistics()
    {
        $user = request()->user();
        $etime = Carbon::parse(request()->query('datetime'));
        $stime = clone $etime;

        $items = StatisticLogModel::query()
            ->select('point_a', 'point_b_monthly as total', 'date')
            ->where('staff_sn', $user->staff_sn)
            ->whereBetween('date', stageBetween($stime->subMonth(4), $etime))
            ->get();

        return $items->map(function ($item) {
            $item->month = Carbon::parse($item->date)->month;

            return $item;
        })->toArray();
    }

    /**
     * 获取积分列表.
     *
     * @author 28youth
     * @param  \Illuminate\Http\Request $request
     * @return mixed
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $items = PointLogModel::query()
            ->where('staff_sn', $user->staff_sn)
            ->filterByQueryString()
            ->withPagination();

        return response()->json($items);
    }

    /**
     * 积分详情.
     * 
     * @author 28youth
     * @param  \App\Models\PointLog $pointlog
     * @return mixed
     */
    public function detail(PointLogModel $pointlog)
    {
        $pointlog->load('source');

        return response()->json($pointlog);
    }
}