<?php
namespace pavlm\yii\stats\data;

use yii\base\BaseObject;
use pavlm\yii\stats\factories\TimeSeriesProviderFactory;
use yii\base\InvalidConfigException;

class MergedStatsProvider extends BaseObject implements TimeSeriesProvider
{
    /**
     * @var TimeSeriesProviderFactory[]
     */
    public $statsProviderFactories;
    
    /**
     * @var callable
     */
    public $mergeCallback;

    /**
     * @var \DateInterval
     */
    public $period;
    
    /**
     * @var \DateTime
     */
    public $rangeStart;
    
    /**
     * @var \DateTime
     */
    public $rangeEnd;

    /**
     * @var \DateTimeZone - target time zone
     */
    public $timeZone;
    
    /**
     * @var TimeSeriesProvider[]
     */
    protected $statsProviders;
    
    public function init()
    {
        if (!$this->period || !$this->rangeStart || !$this->rangeEnd || !$this->timeZone ||
            !$this->statsProviderFactories || !$this->mergeCallback) {
            throw new InvalidConfigException();
        }
        $this->statsProviders = array_map(function (TimeSeriesProviderFactory $factory) {
            return $factory->create($this->rangeStart, $this->rangeEnd, $this->period, $this->timeZone);
        }, $this->statsProviderFactories);
    }
    
    /**
     * @return \DateInterval
     */
    public function getPeriod()
    {
        return $this->period;
    }
    
    /**
     * @return \DateTime
     */
    public function getRangeStart()
    {
        return $this->rangeStart;
    }
    
    /**
     * @return \DateTime
     */
    public function getRangeEnd()
    {
        return $this->rangeEnd;
    }
    
    /**
     * @return \DateTimeZone
     */
    public function getTimeZone()
    {
        return $this->timeZone;
    }
    
    /**
     * @return double
     */
    public function getTotalValue()
    {
        $totals = array_map(function (TimeSeriesProvider $provider) {
            return $provider->getTotalValue();
        }, $this->statsProviders);
        return call_user_func_array($this->mergeCallback, $totals);
    }
    
    public function getIterator()
    {
        $it = new \MultipleIterator();
        foreach ($this->statsProviders as $provider) {
            $it->attachIterator($provider->getIterator());
        }
        foreach ($it as $mitem) {
            $values = array_map(function ($subItem) {
                return $subItem['value'];
            }, $mitem);
            $mergedValue = call_user_func_array($this->mergeCallback, $values);
            $item = reset($mitem);
            $item['value'] = $mergedValue;
            yield $item;
        }
    }
    
}