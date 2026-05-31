<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('report-jobs.stats', fn ($user) => $user !== null);
