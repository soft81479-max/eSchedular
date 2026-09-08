<?php

namespace App\Services\Shared;

use App\Models\Event;

use App\Models\User;
use App\Models\EventType;
use Illuminate\Support\Str;

class MyWorkspaceBuilder {

    public const SURFACE_ADMIN          = 'admin';
    public const SURFACE_REPRESENTATIVE = 'representative';

    /**
     * roles.name => surface. Several roles can share one surface — e.g.
     * every admin-panel role sees the same tab set/routes and is
     * differentiated by permission (below), not by a separate map.
     *
     * Adjust 'organization' if it turns out to need its own layout
     * rather than sharing the representative surface.
     */
    protected array $roleSurfaces = [
        'super_admin'    => self::SURFACE_ADMIN,
        'admin'          => self::SURFACE_ADMIN,
        'operations'     => self::SURFACE_ADMIN,
        'organizer'      => self::SURFACE_ADMIN,
        'representative' => self::SURFACE_REPRESENTATIVE,
        'organization'   => self::SURFACE_REPRESENTATIVE,
    ];

    /**
     * event_types boolean column => module key. Shared by every
     * surface — a DB flag is mapped to a module exactly once.
     */
    protected array $capabilityModules = [
        'compatibility_engine_enabled' => 'matchmaking',
        'meeting_approval_required'    => 'meeting_approvals',
        'has_exhibitors'                => 'booths',
    ];

    /**
     * Presentation, per surface: module key => tab definition
     * (or module key => [tab, tab, ...] for one module that expands
     * into several tabs on a given surface — see 'meetings' below
     * under 'representative').
     *
     * A module key with no entry under a surface is simply not shown
     * there — that's how 'representative' ends up with fewer tabs
     * than 'admin' without needing its own copy of the type/flag logic.
     */
    protected array $modules = [

        self::SURFACE_ADMIN => [
            'overview' => [
                'key' => 'overview', 'title' => 'Overview', 'icon' => 'ph-squares-four',
                'route_name' => 'events.overview.index', 'active' => 'events.overview.*',
            ],
            'organizations' => [
                'key' => 'organizations', 'title' => 'Organizations', 'icon' => 'ph-buildings',
                'route_name' => 'events.organizations.index', 'active' => 'events.organizations.*',
            ],
            'participants' => [
                'key' => 'participants', 'title' => 'Participants', 'icon' => 'ph-users',
                'route_name' => 'events.participants.index', 'active' => 'events.participants.*',
            ],
            'schedules' => [
                'key' => 'schedules', 'title' => 'Schedules', 'icon' => 'ph-calendar',
                'route_name' => 'events.schedules.index', 'active' => 'events.schedules.*',
            ],
            'tracks' => [
                'key' => 'tracks', 'title' => 'Tracks', 'icon' => 'ph-tree-structure',
                'route_name' => 'events.tracks.index', 'active' => 'events.tracks.*',
            ],
            'sessions' => [
                'key' => 'sessions', 'title' => 'Sessions', 'icon' => 'ph-chalkboard-teacher',
                'route_name' => 'events.sessions.index', 'active' => 'events.sessions.*',
            ],
            'speakers' => [
                'key' => 'speakers', 'title' => 'Speakers', 'icon' => 'ph-microphone-stage',
                'route_name' => 'events.speakers.index', 'active' => 'events.speakers.*',
            ],
            'sponsors' => [
                'key' => 'sponsors', 'title' => 'Sponsors', 'icon' => 'ph-medal',
                'route_name' => 'events.sponsors.index', 'active' => 'events.sponsors.*',
            ],
            'booths' => [
                'key' => 'booths', 'title' => 'Booths', 'icon' => 'ph-storefront',
                'route_name' => 'events.booths.index', 'active' => 'events.booths.*',
            ],
            'availability' => [
                'key' => 'availability', 'title' => 'Availability', 'icon' => 'ph-clock',
                'route_name' => 'events.availability.index', 'active' => 'events.availability.*',
            ],
            'matchmaking' => [
                'key' => 'matchmaking', 'title' => 'Matchmaking', 'icon' => 'ph-shuffle',
                'route_name' => 'events.matchmaking.index', 'active' => 'events.matchmaking.*',
            ],
            'meeting_approvals' => [
                'key' => 'meeting_approvals', 'title' => 'Meeting Approvals', 'icon' => 'ph-check-circle',
                'route_name' => 'events.meeting-approvals.index', 'active' => 'events.meeting-approvals.*',
            ],
            'meetings' => [
                'key' => 'meetings', 'title' => 'Meetings', 'icon' => 'ph-handshake',
                'route_name' => 'events.meetings.index', 'active' => 'events.meetings.*',
            ],
            'reports' => [
                'key' => 'reports', 'title' => 'Reports', 'icon' => 'ph-chart-bar',
                'route_name' => 'events.reports.index', 'active' => 'events.reports.*',
            ],
            'teams' => [
                'key' => 'teams', 'title' => 'Teams', 'icon' => 'ph-users-three',
                'route_name' => 'events.teams.index', 'active' => 'events.teams.*',
            ],
            'mentors' => [
                'key' => 'mentors', 'title' => 'Mentors', 'icon' => 'ph-user-list',
                'route_name' => 'events.mentors.index', 'active' => 'events.mentors.*',
            ],
            'submissions' => [
                'key' => 'submissions', 'title' => 'Submissions', 'icon' => 'ph-upload',
                'route_name' => 'events.submissions.index', 'active' => 'events.submissions.*',
            ],
            'judging' => [
                'key' => 'judging', 'title' => 'Judging', 'icon' => 'ph-gavel',
                'route_name' => 'events.judging.index', 'active' => 'events.judging.*',
            ],
            'startups' => [
                'key' => 'startups', 'title' => 'Startups', 'icon' => 'ph-rocket-launch',
                'route_name' => 'events.startups.index', 'active' => 'events.startups.*',
            ],
            'investors' => [
                'key' => 'investors', 'title' => 'Investors', 'icon' => 'ph-chart-line-up',
                'route_name' => 'events.investors.index', 'active' => 'events.investors.*',
            ],
            'pitches' => [
                'key' => 'pitches', 'title' => 'Pitches', 'icon' => 'ph-presentation',
                'route_name' => 'events.pitches.index', 'active' => 'events.pitches.*',
            ],
            'candidates' => [
                'key' => 'candidates', 'title' => 'Candidates', 'icon' => 'ph-user',
                'route_name' => 'events.candidates.index', 'active' => 'events.candidates.*',
            ],
            'interviews' => [
                'key' => 'interviews', 'title' => 'Interviews', 'icon' => 'ph-chats-circle',
                'route_name' => 'events.interviews.index', 'active' => 'events.interviews.*',
            ],
            'institutions' => [
                'key' => 'institutions', 'title' => 'Institutions', 'icon' => 'ph-student',
                'route_name' => 'events.institutions.index', 'active' => 'events.institutions.*',
            ],
            'products' => [
                'key' => 'products', 'title' => 'Products', 'icon' => 'ph-package',
                'route_name' => 'events.products.index', 'active' => 'events.products.*',
            ],
            'projects' => [
                'key' => 'projects', 'title' => 'Projects', 'icon' => 'ph-buildings',
                'route_name' => 'events.projects.index', 'active' => 'events.projects.*',
            ],
            'franchises' => [
                'key' => 'franchises', 'title' => 'Franchises', 'icon' => 'ph-storefront',
                'route_name' => 'events.franchises.index', 'active' => 'events.franchises.*',
            ],
        ],

        self::SURFACE_REPRESENTATIVE => [
            'overview' => [
                'key' => 'overview', 'title' => 'Overview', 'icon' => 'ph-house',
                'route_name' => 'my-events.overview.index',
            ],
            'availability' => [
                'key' => 'availability', 'title' => 'Availability', 'icon' => 'ph-calendar-check',
                'route_name' => 'my-events.availability.index',
            ],
            'schedules' => [
                'key' => 'schedule', 'title' => 'Schedule', 'icon' => 'ph-calendar',
                'route_name' => 'my-events.schedule.index',
            ],
            'participants' => [
                'key' => 'participants', 'title' => 'Participants', 'icon' => 'ph-identification-card',
                'route_name' => 'my-events.participants.index',
            ],
            'organizations' => [
                'key' => 'organizations', 'title' => 'Organizations', 'icon' => 'ph-buildings',
                'route_name' => 'my-events.organizations.index',
            ],
            'matchmaking' => [
                'key' => 'matchmaking', 'title' => 'Matchmaking', 'icon' => 'ph-users-three',
                'route_name' => 'my-events.matchmaking.index',
            ],
            'meetings' => [
                /*
                [
                    'key' => 'meeting_requests', 'title' => 'Requests', 'icon' => 'ph-envelope-simple',
                    'route_name' => 'my-events.meeting-requests.index',
                ],
                */
                [
                    'key' => 'meetings', 'title' => 'Meetings', 'icon' => 'ph-handshake',
                    'route_name' => 'my-events.meetings.index',
                ],
            ],
        ],
    ];

    /**
     * Build the toolbar for an event, for the given surface. Pass the
     * viewing user (or leave null to fall back to auth()->user()) so
     * any tool with a 'permission' key can be filtered per role.
     *
     *     $builder->get($event, ToolbarBuilder::SURFACE_ADMIN);
     *     $builder->get($event, ToolbarBuilder::SURFACE_REPRESENTATIVE);
     */
    protected function build(Event $event,string $surface = self::SURFACE_ADMIN,? User $user = null): array {
        if (! isset($this->modules[$surface])) {
            throw new \InvalidArgumentException(
                "MyWorkspaceBuilder: unknown surface [{$surface}]."
            );
        }

        return $this->presentModules(
            $this->resolveModules($event),
            $surface,
            $user
        );
    }

    /**
     * Convenience entry point: derive the surface from the user's role
     * instead of the caller having to know/pass it. This is what your
     * controllers should call in practice — `get()` above stays around
     * for tests or anywhere you need to force a specific surface.
     *
     *     $builder->getForUser($event, auth()->user());
     */
    public function buildForUser(Event $event, User $user): array {
        /*
        |--------------------------------------------------------------------------
        | Resolve Surface
        |--------------------------------------------------------------------------
        */
        $role = $user->roleName()?->name;

        if (! $role || ! isset($this->roleSurfaces[$role])) {
            throw new \InvalidArgumentException(
                "MyWorkspaceBuilder: no surface mapped for role [{$role}]."
            );
        }

        return $this->build(
            $event,
            $this->roleSurfaces[$role],
            $user
        );
    }

    /**
     * Resolve the semantic module keys for an event (type + flags +
     * per-event overrides). Same regardless of surface.
     */
    protected function resolveModules(Event $event): array {
        $eventType = $event->eventType;

        $method = Str::of($eventType->slug)
            ->replace(['-', '_'], ' ')
            ->camel()
            ->toString();

        if (! method_exists($this, $method)) {
            report(new \RuntimeException(
                "ToolbarBuilder: no module mapping defined for event type slug [{$eventType->slug}] (event #{$event->id}); falling back to default toolbar."
            ));
            $modules = $this->default();
        } else {
            $modules = $this->{$method}();
        }

        $modules = array_unique([...$modules, ...$this->deriveCapabilityModules($eventType)]);

        $overrides = $event->toolbar_overrides ?? [];
        $modules = array_unique([...$modules, ...($overrides['add'] ?? [])]);
        $modules = array_diff($modules, $overrides['remove'] ?? []);

        return array_values($modules);
    }

    protected function presentModules(array $moduleKeys, string $surface, $user = null): array {
        $presentation = $this->modules[$surface];
        $items = [];

        foreach ($moduleKeys as $key) {
            $definition = $presentation[$key] ?? null;

            if ($definition === null) {
                continue;
            }

            // Single tab vs. one module expanding into several tabs.
            $candidates = isset($definition['key']) ? [$definition] : $definition;

            foreach ($candidates as $tab) {
                if ($this->userMayView($tab, $user)) {
                    unset($tab['permission']);
                    $items[] = $tab;
                }
            }
        }

        return $items;
    }

    /**
     * A tab with no 'permission' key is visible to anyone who can reach
     * its surface. Add 'permission' => 'events.matchmaking.manage' to
     * any tab definition above to gate it further within a surface —
     * e.g. differentiating Admin from Operations on the same admin
     * surface without giving Operations its own presentation map.
     */
    protected function userMayView(array $tab, $user = null): bool {
        if (! isset($tab['permission'])) {
            return true;
        }

        $user ??= auth()->user();

        return $user?->canPermission($tab['permission']) ?? false;
    }

    protected function deriveCapabilityModules(EventType $eventType): array {
        return collect($this->capabilityModules)
            ->filter(fn ($module, $flag) => (bool) $eventType->{$flag})
            ->values()
            ->all();
    }

    protected function default(): array {
        return ['overview'];
    }

    /*
    |--------------------------------------------------------------------------
    | Knowledge Events
    |--------------------------------------------------------------------------
    */

    protected function workshop(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'participants',
            'schedules',
            'tracks',
            'sessions',
            'meetings',
        ];
    }

    protected function seminar(): array
    {
        return $this->workshop();
    }

    protected function symposium(): array
    {
        return $this->conference();
    }

    protected function panelDiscussion(): array
    {
        return $this->conference();
    }

    /*
    |--------------------------------------------------------------------------
    | Conference Style
    |--------------------------------------------------------------------------
    */

    protected function conference(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'participants',
            'schedules',
            'tracks',
            'sessions',
            'speakers',
            'sponsors',
            'meetings',
        ];
    }

    protected function summit(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'participants',
            'sessions',
            'speakers',
            'meetings',
        ];
    }

    protected function healthcareConference(): array
    {
        return $this->conference();
    }

    protected function governmentSummit(): array
    {
        return $this->summit();
    }

    /*
    |--------------------------------------------------------------------------
    | Networking Events
    |--------------------------------------------------------------------------
    */

    protected function forum(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'participants',
            'meetings',
        ];
    }

    protected function roundTable(): array
    {
        return $this->forum();
    }

    protected function networkingEvent(): array
    {
        return [
            'overview',
            'organizations',
            'participants',
            'availability',
            'meetings',
        ];
    }

    protected function b2bMeetings(): array
    {
        return $this->networkingEvent();
    }

    protected function ngoForum(): array
    {
        return $this->forum();
    }

    /*
    |--------------------------------------------------------------------------
    | Expo Events
    |--------------------------------------------------------------------------
    */

    protected function expo(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'participants',
            'booths',
            'meetings',
        ];
    }

    protected function tradeFair(): array
    {
        return $this->expo();
    }

    protected function technologyExpo(): array
    {
        return $this->expo();
    }

    protected function medicalExpo(): array
    {
        return $this->expo();
    }

    protected function agricultureExpo(): array
    {
        return $this->expo();
    }

    protected function tourismExpo(): array
    {
        return $this->expo();
    }

    protected function manufacturingExpo(): array
    {
        return $this->expo();
    }

    /*
    |--------------------------------------------------------------------------
    | Specialized Expo Events
    |--------------------------------------------------------------------------
    */

    protected function realEstateExpo(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'booths',
            'projects',
            'meetings',
        ];
    }

    protected function educationExpo(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'booths',
            'institutions',
            'meetings',
        ];
    }

    protected function franchiseExpo(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'booths',
            'franchises',
            'meetings',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Startup Ecosystem
    |--------------------------------------------------------------------------
    */

    protected function startupSummit(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'startups',
            'investors',
            'pitches',
            'meetings',
        ];
    }

    protected function investorMeet(): array
    {
        return $this->startupSummit();
    }

    protected function pitchEvent(): array
    {
        return [
            'overview',
            'organizations',
            'startups',
            'investors',
            'pitches',
            'judging',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Innovation Events
    |--------------------------------------------------------------------------
    */

    protected function hackathon(): array
    {
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

    protected function innovationChallenge(): array
    {
        return [
            'overview',
            'teams',
            'participants',
            'mentors',
            'submissions',
            'judging',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Recruitment Events
    |--------------------------------------------------------------------------
    */

    protected function jobFair(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'candidates',
            'interviews',
            'meetings',
        ];
    }

    protected function careerExpo(): array
    {
        return [
            'overview',
            'organizations',
            'availability',
            'booths',
            'candidates',
            'interviews',
            'meetings',
        ];
    }
}