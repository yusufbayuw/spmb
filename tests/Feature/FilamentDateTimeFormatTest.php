<?php

namespace Tests\Feature;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Tests\TestCase;

class FilamentDateTimeFormatTest extends TestCase
{
    public function test_datetime_picker_uses_non_native_24_hour_format_by_default(): void
    {
        $picker = DateTimePicker::make('scheduled_at');

        $this->assertFalse($picker->isNative());
        $this->assertFalse($picker->hasSeconds());
        $this->assertSame(config('app.timezone'), $picker->getTimezone());
        $this->assertSame('d/m/Y H:i', $picker->getDisplayFormat());
    }

    public function test_time_picker_uses_24_hour_format_by_default(): void
    {
        $picker = TimePicker::make('time');

        $this->assertFalse($picker->isNative());
        $this->assertFalse($picker->hasSeconds());
        $this->assertSame('H:i', $picker->getDisplayFormat());
    }
}
