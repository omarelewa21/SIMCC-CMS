<?php
namespace App\Traits;

trait ComputeOptions
{
    private array $computeOptions = ['award', 'country_rank', 'school_rank', 'global_rank', 'remark'];

    private function getComputeOptions(): array
    {
        $notToCompute = request('not_to_compute') ?? [];
        return array_diff($this->computeOptions, $notToCompute);
    }

    private function willCompute(string $option): bool
    {
        return in_array($option, $this->requestComputeOptions);
    }

    private function willComputeAny(array $options): bool
    {
        return count(array_intersect($options, $this->requestComputeOptions)) > 0;
    }

    private function willComputeAll(array $options): bool
    {
        return count(array_intersect($options, $this->requestComputeOptions)) === count($options);
    }

    private function willNotCompute(string|array $options): bool
    {
        return is_array($options)
            ? count(array_intersect($options, $this->requestComputeOptions)) === 0
            : !$this->willCompute($options);
    }
}
