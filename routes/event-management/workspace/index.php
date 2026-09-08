<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth','event.access',])
     ->prefix('events/{event}')
     ->name('events.')
     ->group(function () {
          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          require __DIR__.'/overview.php';
          require __DIR__.'/organizations.php';
          require __DIR__.'/representatives.php';
          require __DIR__.'/participants.php';
          require __DIR__.'/schedules.php';
          require __DIR__.'/tracks.php';
          require __DIR__.'/sessions.php';
          require __DIR__.'/speakers.php';
          require __DIR__.'/sponsors.php';
          require __DIR__.'/booths.php';
          require __DIR__.'/availability.php';
          require __DIR__.'/matchmaking.php';
          require __DIR__.'/meetings.php';
          //require __DIR__.'/reports.php';

          /*
          |--------------------------------------------------------------------------
          | Startup Ecosystem
          |--------------------------------------------------------------------------
          */
          //require __DIR__.'/startups.php';
          //require __DIR__.'/investors.php';
          //require __DIR__.'/pitches.php';

          /*
          |--------------------------------------------------------------------------
          | Recruitment
          |--------------------------------------------------------------------------
          */
          //require __DIR__.'/candidates.php';
          //require __DIR__.'/interviews.php';

          /*
          |--------------------------------------------------------------------------
          | Education
          |--------------------------------------------------------------------------
          */
          //require __DIR__.'/institutions.php';

          /*
          |--------------------------------------------------------------------------
          | Exhibition
          |--------------------------------------------------------------------------
          */
          //require __DIR__.'/products.php';
          //require __DIR__.'/projects.php';
          //require __DIR__.'/franchises.php';

          /*
          |--------------------------------------------------------------------------
          | Innovation
          |--------------------------------------------------------------------------
          */
          //require __DIR__.'/teams.php';
          //require __DIR__.'/mentors.php';
          //require __DIR__.'/submissions.php';
          //require __DIR__.'/judging.php';
});