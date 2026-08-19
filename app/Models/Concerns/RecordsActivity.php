<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Every model holding business data logs its changes.
 *
 * Sensitive attributes are excluded here rather than at the call site, so a new
 * controller cannot accidentally write a religion answer or a restricted reason
 * into the audit log by touching the model.
 */
trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->activityLogAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->activityLogName());
    }

    /** @return array<int, string> */
    protected function activityLogAttributes(): array
    {
        return property_exists($this, 'logAttributes') && $this->logAttributes !== []
            ? $this->logAttributes
            : ['*'];
    }

    protected function activityLogName(): string
    {
        return property_exists($this, 'logName')
            ? $this->logName
            : class_basename($this);
    }
}
