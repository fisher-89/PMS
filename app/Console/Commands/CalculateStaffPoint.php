<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\PointLogSource;
use function App\monthBetween;
use Illuminate\Console\Command;
use App\Models\ArtisanCommandLog;
use App\Models\PointLog as PointLogModel;
use App\Jobs\StatisticPoint as StatisticPointJob;
use App\Jobs\StatisticLogPoint as StatisticLogPointJob;
use App\Models\PersonalPointStatistic as StatisticModel;
use App\Models\PersonalPointStatisticLog as StatisticLogModel;

class CalculateStaffPoint extends Command
{
    /**
     * 日结统计数据.
     * 
     * @var array
     */
    protected $daily;

    /**
     * 月结统计数据.
     * 
     * @var array
     */
    protected $monthly;

    protected $signature = 'pms:calculate-staff-point';
    protected $description = 'Calculate staff point';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->calculateMonthPoint();
    }

    public function calculateMonthPoint()
    {
        $now = now();
        if ($this->preNode() !== null) {
            // 获取所有日结数据进行结算
            StatisticModel::get()->map(function ($item) {
                // 判断跨月清空数据
                if (!Carbon::parse($this->preNode()->created_at)->isCurrentMonth()) {
                    // 放入月结数据
                    $this->monthly[$item->staff_sn] = $item->toArray();

                    $item->point_a = 0;
                    $item->source_a_monthly = $this->makeSourceData();
                    $item->point_b_monthly = 0;
                    $item->source_b_monthly = $this->makeSourceData();
                }
                $this->daily[$item->staff_sn] = $item->toArray();
            });
        }

        // 拿上次统计到现在的积分日志
        $logs = PointLogModel::query()
            ->select('point_a', 'point_b', 'staff_sn', 'source_id', 'changed_at')
            ->when($this->preNode(), function ($query) use ($now) {
                $query->whereBetween('created_at', [$this->preNode()->created_at, $now]);
            })
            ->get();

        $logs->map(function ($item) use ($now) {

            $this->handleLastMonthlyStatisticData($item);

            $this->handleLastStatisticData($item, $now);
        });

        $commandModel = $this->createLog();
        try {
            \DB::beginTransaction();

            if ($this->daily) {
                foreach ($this->daily as $key => $day) {
                    StatisticPointJob::dispatch($day, $key);
                }
            }

            if ($this->monthly) {
                foreach ($this->monthly as $key => $month) {
                    StatisticLogPointJob::dispatch($month, $key);
                }
            }

            $commandModel->status = 1;
            $commandModel->save();

            \DB::commit();
        } catch (Exception $e) {
            \DB::rollBack();

            $commandModel->status = 2;
            $commandModel->save();
        }
    }

    /**
     * 上次结算节点信息.
     * 
     * @author 28youth
     * @return \App\Models\ArtisanCommandLog|null
     */
    public function preNode()
    {
        return ArtisanCommandLog::query()
            ->bySn('pms:calculate-staff-point')
            ->where('status', 1)
            ->latest('id')
            ->first();
    }

    /**
     * 创建积分日志.
     *
     * @author 28youth
     * @return ArtisanCommandLog
     */
    public function createLog() : ArtisanCommandLog
    {
        $artisan = new ArtisanCommandLog();
        $artisan->command_sn = 'pms:calculate-staff-point';
        $artisan->created_at = Carbon::now();
        $artisan->title = '每月积分结算';
        $artisan->status = 0;
        $artisan->save();

        return $artisan;
    }

    /**
     * 处理上月统计数据.
     * 
     * @author 28youth
     * @param  $log
     */
    public function handleLastMonthlyStatisticData($log)
    {
        // 非本月生效的积分日志
        if (!Carbon::parse($log->changed_at)->isCurrentMonth()) {

            // 是否存在上月结算员工
            if (isset($this->monthly[$log->staff_sn])) {
                $this->monthly[$log->staff_sn]['point_a'] += $log->point_a;
                $this->monthly[$log->staff_sn]['point_a_total'] += $log->point_a;
                $this->monthly[$log->staff_sn]['source_a_monthly'] = $this->monthlySource($log, 'source_a_monthly');
                $this->monthly[$log->staff_sn]['source_a_total'] = $this->monthlySource($log, 'source_a_total');

                $this->monthly[$log->staff_sn]['point_b_monthly'] += $log->point_b;
                $this->monthly[$log->staff_sn]['point_b_total'] += $log->point_b;
                $this->monthly[$log->staff_sn]['source_b_monthly'] = $this->monthlySource($log, 'source_b_monthly');
                $this->monthly[$log->staff_sn]['source_b_total'] = $this->monthlySource($log, 'source_b_total');
            } else {
                $this->monthly[$log->staff_sn]['point_a'] = $log->point_a;
                $this->monthly[$log->staff_sn]['point_a_total'] = $log->point_a;
                $this->monthly[$log->staff_sn]['source_a_monthly'] = $this->monthlySource($log, 'source_a_monthly');
                $this->monthly[$log->staff_sn]['source_a_total'] = $this->monthlySource($log, 'source_a_total');

                $this->monthly[$log->staff_sn]['point_b_monthly'] = $log->point_b;
                $this->monthly[$log->staff_sn]['point_b_total'] = $log->point_b;
                $this->monthly[$log->staff_sn]['source_b_monthly'] = $this->monthlySource($log, 'source_b_monthly');
                $this->monthly[$log->staff_sn]['source_b_total'] = $this->monthlySource($log, 'source_b_total');
            }
        }
    }

    /**
     * 处理上次统计数据.
     * 
     * @author 28youth
     * @param  $log
     */
    public function handleLastStatisticData($log, $now)
    {
        // 是否存在上次结算员工
        if (isset($this->daily[$log->staff_sn])) {
            $this->daily[$log->staff_sn]['point_a'] += $log->point_a;
            $this->daily[$log->staff_sn]['point_a_total'] += $log->point_a;
            $this->daily[$log->staff_sn]['source_a_monthly'] = $this->monthlySource($log, 'source_a_monthly', 'daily');
            $this->daily[$log->staff_sn]['source_a_total'] = $this->monthlySource($log, 'source_a_total', 'daily');

            $this->daily[$log->staff_sn]['point_b_monthly'] += $log->point_b;
            $this->daily[$log->staff_sn]['point_b_total'] += $log->point_b;
            $this->daily[$log->staff_sn]['source_b_monthly'] = $this->monthlySource($log, 'source_b_monthly', 'daily');
            $this->daily[$log->staff_sn]['source_b_total'] = $this->monthlySource($log, 'source_b_total', 'daily');
        } else {
            $this->daily[$log->staff_sn]['point_a'] = $log->point_a;
            $this->daily[$log->staff_sn]['point_a_total'] = $log->point_a;
            $this->daily[$log->staff_sn]['source_a_monthly'] = $this->monthlySource($log, 'source_a_monthly', 'daily');
            $this->daily[$log->staff_sn]['source_a_total'] = $this->monthlySource($log, 'source_a_total', 'daily');

            $this->daily[$log->staff_sn]['point_b_monthly'] = $log->point_b;
            $this->daily[$log->staff_sn]['point_b_total'] = $log->point_b;
            $this->daily[$log->staff_sn]['source_b_monthly'] = $this->monthlySource($log, 'source_b_monthly', 'daily');
            $this->daily[$log->staff_sn]['source_b_total'] = $this->monthlySource($log, 'source_b_total', 'daily');
        }

        $current['date'] = $now;
    }

    /**
     * 初始化默认积分来源信息.
     * 
     * @author 28youth
     * @return array
     */
    public function makeSourceData()
    {
        $cacheKey = 'default_point_log_source';

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        $source = PointLogSource::get()->map(function ($item) {
            $item->add_point = 0;
            $item->sub_point = 0;
            $item->add_a_point = 0;
            $item->sub_a_point = 0;
            $item->point_a_total = 0;
            $item->point_b_total = 0;

            return $item;
        })->toArray();

        $expiresAt = now()->addDay();
        Cache::put($cacheKey, $source, $expiresAt);

        return $source;
    }

    /**
     * 来源积分统计.
     *
     * @author 28youth
     * @return array
     */
    public function monthlySource($log, $type, $cate = 'monthly')
    {
        $current = $this->{$cate}[$log->staff_sn][$type] ?? $this->makeSourceData();

        $fields = ['add_point', 'sub_point', 'add_a_point', 'sub_a_point', 'point_a_total', 'point_b_total'];

        foreach ($current as $k => &$v) {
            
            // start 兼容性代码后期可以去掉
            foreach ($fields as $key => $field) {
                $v[$field] = $v[$field] ?? 0;
            }
            // end 兼容代码

            if ($v['id'] === $log->source_id) {
                $v['point_a_total'] += $log->point_a;
                $v['point_b_total'] += $log->point_b;
                if ($log->point_a >= 0) {
                    $v['add_a_point'] += $log->point_a;
                } else {
                    $v['sub_a_point'] += $log->point_a;
                }
                if ($log->point_b >= 0) {
                    $v['add_point'] += $log->point_b;
                } else {
                    $v['sub_point'] += $log->point_b;
                }
            }
        }
        return $current;
    }
}
