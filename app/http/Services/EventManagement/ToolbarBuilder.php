<?php

namespace App\Services\EventManagement;

use App\Models\Event;
use Illuminate\Support\Str;

class ToolbarBuilder {
    /**
     * Master Module Definitions
     */
    protected array $modules = [
        'overview' => [
            'key'        => 'overview',
            'title'      => 'Overview',
            'icon'       => 'ph-squares-four',
            'route_name' => 'events.overview.index',
            'active'     => 'events.overview.*',
        ],

        'organizations' => [
            'key'        => 'organizations',
            'title'      => 'Organizations',
            'icon'       => 'ph-buildings',
            'route_name' => 'events.organizations.index',
            'active'     => 'events.organizations.*',
        ],

        'participants' => [
            'key'        => 'participants',
            'title'      => 'Participants',
            'icon'       => 'ph-users',
            'route_name' => 'events.participants.index',
            'active'     => 'events.participants.*',
        ],

        'schedules' => [
            'key'        => 'schedules',
            'title'      => 'Schedules',
            'icon'       => 'ph-calendar',
            'route_name' => 'events.schedules.index',
            'active'     => 'events.schedules.*',
        ],

        'tracks' => [
            'key'        => 'tracks',
            'title'      => 'Tracks',
            'icon'       => 'ph-tree-structure',
            'route_name' => 'events.tracks.index',
            'active'     => 'events.tracks.*',
        ],

        'sessions' => [
            'key'        => 'sessions',
            'title'      => 'Sessions',
            'icon'       => 'ph-chalkboard-teacher',
            'route_name' => 'events.sessions.index',
            'active'     => 'events.sessions.*',
        ],

        'speakers' => [
            'key'        => 'speakers',
            'title'      => 'Speakers',
            'icon'       => 'ph-microphone-stage',
            'route_name' => 'events.speakers.index',
            'active'     => 'events.speakers.*',
        ],

        'sponsors' => [
            'key'        => 'sponsors',
            'title'      => 'Sponsors',
            'icon'       => 'ph-medal',
            'route_name' => 'events.sponsors.index',
            'active'     => 'events.sponsors.*',
        ],

        'booths' => [
            'key'        => 'booths',
            'title'      => 'Booths',
            'icon'       => 'ph-storefront',
            'route_name' => 'events.booths.index',
            'active'     => 'events.booths.*',
        ],

        'availability' => [
            'key'        => 'availability',
            'title'      => 'Availability',
            'icon'       => 'ph-clock',
            'route_name' => 'events.availability.index',
            'active'     => 'events.availability.*',
        ],

        'matchmaking' => [
            'key'        => 'matchmaking',
            'title'      => 'Matchmaking',
            'icon'       => 'ph-shuffle',
            'route_name' => 'events.matchmaking.index',
            'active'     => 'events.matchmaking.*',
        ],

        'meetings' => [
            'key'        => 'meetings',
            'title'      => 'Meetings',
            'icon'       => 'ph-handshake',
            'route_name' => 'events.meetings.index',
            'active'     => 'events.meetings.*',
        ],

        'reports' => [
            'key'        => 'reports',
            'title'      => 'Reports',
            'icon'       => 'ph-chart-bar',
            'route_name' => 'events.reports.index',
            'active'     => 'events.reports.*',
        ],

        'teams' => [
            'key'        => 'teams',
            'title'      => 'Teams',
            'icon'       => 'ph-users-three',
            'route_name' => 'events.teams.index',
            'active'     => 'events.teams.*',
        ],

        'mentors' => [
            'key'        => 'mentors',
            'title'      => 'Mentors',
            'icon'       => 'ph-user-list',
            'route_name' => 'events.mentors.index',
            'active'     => 'events.mentors.*',
        ],

        'submissions' => [
            'key'        => 'submissions',
            'title'      => 'Submissions',
            'icon'       => 'ph-upload',
            'route_name' => 'events.submissions.index',
            'active'     => 'events.submissions.*',
        ],

        'judging' => [
            'key'        => 'judging',
            'title'      => 'Judging',
            'icon'       => 'ph-gavel',
            'route_name' => 'events.judging.index',
            'active'     => 'events.judging.*',
        ],

        'startups' => [
            'key'        => 'startups',
            'title'      => 'Startups',
            'icon'       => 'ph-rocket-launch',
            'route_name' => 'events.startups.index',
            'active'     => 'events.startups.*',
        ],

        'investors' => [
            'key'        => 'investors',
            'title'      => 'Investors',
            'icon'       => 'ph-chart-line-up',
            'route_name' => 'events.investors.index',
            'active'     => 'events.investors.*',
        ],

        'pitches' => [
            'key'        => 'pitches',
            'title'      => 'Pitches',
            'icon'       => 'ph-presentation',
            'route_name' => 'events.pitches.index',
            'active'     => 'events.pitches.*',
        ],

        'candidates' => [
            'key'        => 'candidates',
            'title'      => 'Candidates',
            'icon'       => 'ph-user',
            'route_name' => 'events.candidates.index',
            'active'     => 'events.candidates.*',
        ],

        'interviews' => [
            'key'        => 'interviews',
            'title'      => 'Interviews',
            'icon'       => 'ph-chats-circle',
            'route_name' => 'events.interviews.index',
            'active'     => 'events.interviews.*',
        ],

        'institutions' => [
            'key'        => 'institutions',
            'title'      => 'Institutions',
            'icon'       => 'ph-student',
            'route_name' => 'events.institutions.index',
            'active'     => 'events.institutions.*',
        ],

        'products' => [
            'key'        => 'products',
            'title'      => 'Products',
            'icon'       => 'ph-package',
            'route_name' => 'events.products.index',
            'active'     => 'events.products.*',
        ],

        'projects' => [
            'key'        => 'projects',
            'title'      => 'Projects',
            'icon'       => 'ph-buildings',
            'route_name' => 'events.projects.index',
            'active'     => 'events.projects.*',
        ],

        'franchises' => [
            'key'        => 'franchises',
            'title'      => 'Franchises',
            'icon'       => 'ph-storefront',
            'route_name' => 'events.franchises.index',
            'active'     => 'events.franchises.*',
        ],
    ];

    /**
     * Build toolbar.
     */
    public function get(Event $event): array {
        $method = Str::of($event->eventType->slug)
            ->replace(['-', '_'], ' ')
            ->camel()
            ->toString();

        $modules = method_exists($this, $method)
            ? $this->{$method}()
            : $this->default();

        return array_values(array_filter(array_map(
            fn ($module) => $this->modules[$module] ?? null,
            $modules
        )));
    }

    /**
     * Default Toolbar
     */
    protected function default(): array {
        return [
            'overview',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Event Types
    |--------------------------------------------------------------------------
    */
    /**
     * Workshop
     */
    protected function workshop(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'schedules',
            'tracks',
            'sessions',
            'matchmaking',
            'meetings',
        ];
    }

    /**
     * Seminar
     */
    protected function seminar(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'schedules',
            'sessions',
            'meetings',
        ];
    }

    /**
     * Forum
     */
    protected function forum(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'matchmaking',
            'meetings',
        ];
    }

    /**
     * Conference
     */
    protected function conference(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'schedules',
            'tracks',
            'sessions',
            'speakers',
            'sponsors',
            'meetings',
        ];
    }

    /**
     * Summit
     */
    protected function summit(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'sessions',
            'speakers',
            'meetings',
        ];
    }

    /**
     * Expo
     */
    protected function expo(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'booths',
            'meetings',
        ];
    }

    /**
     * B2B Meetings
     */
    protected function b2bMeetings(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'availability',
            'matchmaking',
            'meetings',
        ];
    }

    /**
     * Symposium
     */
    protected function symposium(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'schedules',
            'sessions',
            'speakers',
            'meetings',
        ];
    }

    /**
     * Round Table
     */
    protected function roundTable(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'meetings',
        ];
    }

    /**
     * Panel Discussion
     */
    protected function panelDiscussion(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'sessions',
            'speakers',
        ];
    }

    /**
     * Networking Event
     */
    protected function networkingEvent(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'availability',
            'matchmaking',
            'meetings',
        ];
    }

    /**
     * Trade Fair
     */
    protected function tradeFair(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'booths',
            'meetings',
        ];
    }

    /**
     * Startup Summit
     */
    protected function startupSummit(): array {
        return [
            'overview',
            'organizations',
            'startups',
            'investors',
            'pitches',
            'matchmaking',
            'meetings',
        ];
    }

    /**
     * Investor Meet
     */
    protected function investorMeet(): array {
        return [
            'overview',
            'organizations',
            'startups',
            'investors',
            'pitches',
            'matchmaking',
            'meetings',
        ];
    }

    /**
     * Pitch Event
     */
    protected function pitchEvent(): array {
        return [
            'overview',
            'organizations',
            'startups',
            'investors',
            'pitches',
            'judging',
        ];
    }

    /**
     * Hackathon
     */
    protected function hackathon(): array {
        return [
            'overview',
            'teams',
            'participants',
            'mentors',
            'schedules',
            'sessions',
            'submissions',
            'judging',
        ];
    }

    /**
     * Innovation Challenge
     */
    protected function innovationChallenge(): array {
        return [
            'overview',
            'teams',
            'participants',
            'mentors',
            'submissions',
            'judging',
        ];
    }

        /**
     * Job Fair
     */
    protected function jobFair(): array {
        return [
            'overview',
            'organizations',
            'candidates',
            'interviews',
            'matchmaking',
            'meetings',
        ];
    }

    /**
     * Career Expo
     */
    protected function careerExpo(): array {
        return [
            'overview',
            'organizations',
            'booths',
            'candidates',
            'interviews',
            'meetings',
        ];
    }

    /**
     * Education Expo
     */
    protected function educationExpo(): array {
        return [
            'overview',
            'organizations',
            'booths',
            'institutions',
            'meetings',
        ];
    }

    /**
     * Healthcare Conference
     */
    protected function healthcareConference(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'sessions',
            'speakers',
            'meetings',
        ];
    }

    /**
     * Medical Expo
     */
    protected function medicalExpo(): array {
        return [
            'overview',
            'organizations',
            'booths',
            'products',
            'meetings',
        ];
    }

    /**
     * Government Summit
     */
    protected function governmentSummit(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'sessions',
            'meetings',
        ];
    }

    /**
     * NGO Forum
     */
    protected function ngoForum(): array {
        return [
            'overview',
            'organizations',
            'participants',
            'matchmaking',
            'meetings',
        ];
    }

    /**
     * Agriculture Expo
     */
    protected function agricultureExpo(): array {
        return [
            'overview',
            'organizations',
            'booths',
            'products',
            'meetings',
        ];
    }

    /**
     * Tourism Expo
     */
    protected function tourismExpo(): array {
        return [
            'overview',
            'organizations',
            'booths',
            'meetings',
        ];
    }

    /**
     * Real Estate Expo
     */
    protected function realEstateExpo(): array {
        return [
            'overview',
            'organizations',
            'booths',
            'projects',
            'meetings',
        ];
    }

    /**
     * Technology Expo
     */
    protected function technologyExpo(): array {
        return [
            'overview',
            'organizations',
            'booths',
            'products',
            'startups',
            'meetings',
        ];
    }

    /**
     * Manufacturing Expo
     */
    protected function manufacturingExpo(): array {
        return [
            'overview',
            'organizations',
            'booths',
            'products',
            'meetings',
        ];
    }

    /**
     * Franchise Expo
     */
    protected function franchiseExpo(): array {
        return [
            'overview',
            'organizations',
            'booths',
            'franchises',
            'meetings',
        ];
    }
}
