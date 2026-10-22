<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardStatsController extends Controller
{
    /**
     * Get aggregated stats and charts data for dashboard
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // 1. Overall stats cards
        $totalProperties = Property::count();
        $availableProperties = Property::whereIn('status', ['new', 'available'])->count();
        $underNegotiationProperties = Property::whereIn('status', ['under_negotiation', 'reserved', 'requested'])->count();
        $unavailableProperties = Property::whereIn('status', ['unavailable', 'off_market', 'draft', 'under_payment', 'under_procedure'])->count();
        $soldProperties = Property::whereIn('status', ['sold', 'closed'])->count();
        $rentedProperties = Property::where('status', 'rented')->count();
        $totalAgents = User::where('role', 'agent')->count();
        $totalClients = User::where('role', 'client')->count();
        $totalTeamLeads = User::where('role', 'team_lead')->count();
        $totalAdmins = User::where('role', 'admin')->count();

        // 2. Quick Status chips
        $newCount = Property::where('status', 'new')->orWhere('is_new', true)->count();
        $updatedCount = Property::where('updated_at', '>=', now()->subDays(7))->count();
        $priceDropCount = Property::whereNotNull('previous_price')->whereColumn('price', '<', 'previous_price')->count();
        $backToMarketCount = Property::where('is_back_to_market', true)->count();
        $needsUpdateCount = Property::whereIn('status', ['new', 'available', 'under_negotiation'])
            ->where('updated_at', '<=', now()->subDays(60))
            ->count();

        // 3. Agent Performance (Top agents and members with their properties counts, visible system-wide)
        $agentsQuery = User::where(function ($q) {
            $q->where('role', 'agent')
              ->orWhere(function ($sub) {
                  $sub->whereIn('role', ['admin', 'team_lead'])
                      ->has('properties');
              });
        })->withCount([
            'properties as total_properties',
            'properties as available_count' => fn($q) => $q->whereIn('status', ['new', 'available']),
            'properties as sold_count' => fn($q) => $q->whereIn('status', ['sold', 'closed', 'rented']),
            'properties as rented_count' => fn($q) => $q->where('status', 'rented'),
        ]);

        $agentsPerformance = $agentsQuery->orderByDesc('total_properties')->take(10)->get([
            'id', 'name', 'email', 'image', 'role', 'team_lead_id'
        ]);

        // 4. Team Leads Structure (Team lead + agent count + total properties)
        $teamLeads = User::where('role', 'team_lead')->with(['agents:id,name,team_lead_id'])->get();
        $teamStructure = $teamLeads->map(function ($lead) {
            $agentIds = $lead->agents->pluck('id')->toArray();
            $teamPropsCount = Property::whereIn('user_id', array_merge([$lead->id], $agentIds))->count();

            return [
                'id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
                'image' => $lead->image ? asset('storage/' . $lead->image) : null,
                'agents_count' => $lead->agents->count(),
                'agents' => $lead->agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
                'properties_count' => $teamPropsCount,
            ];
        });

        // 5. Property Type Distribution
        $typesDistribution = Property::select('property_type', DB::raw('count(*) as count'))
            ->whereNotNull('property_type')
            ->groupBy('property_type')
            ->orderByDesc('count')
            ->get();

        // 6. Area Distribution
        $areasDistribution = Property::select('area_name', DB::raw('count(*) as count'))
            ->whereNotNull('area_name')
            ->groupBy('area_name')
            ->orderByDesc('count')
            ->get();

        // 7. Recent 10 properties (updated or added) - visible to all roles
        $recentProperties = Property::with([
            'owner:id,name,email,role,image',
            'creator:id,name,email,role,image',
            'activities' => fn($q) => $q->latest()->take(1)->with('user:id,name,role')
        ])
            ->orderByDesc('updated_at')
            ->take(10)
            ->get();

        // 8. User's Favorite / Bookmarked properties (last 10)
        $favoriteProperties = [];
        if ($user) {
            $favoriteProperties = Property::whereHas('bookmarks', fn($q) => $q->where('user_id', $user->id))
                ->with([
                    'owner:id,name,email,role,image',
                    'creator:id,name,email,role,image',
                ])
                ->latest()
                ->take(10)
                ->get();
        }

        return response()->json([
            'overview' => [
                'total_properties' => $totalProperties,
                'available_properties' => $availableProperties,
                'under_negotiation_properties' => $underNegotiationProperties,
                'unavailable_properties' => $unavailableProperties,
                'under_payment_properties' => $unavailableProperties,
                'sold_properties' => $soldProperties,
                'rented_properties' => $rentedProperties,
                'total_agents' => $totalAgents,
                'total_clients' => $totalClients,
                'total_team_leads' => $totalTeamLeads,
                'total_admins' => $totalAdmins,
                'needs_update_properties' => $needsUpdateCount,
            ],
            'recent_properties' => $recentProperties,
            'favorite_properties' => $favoriteProperties,
            'status_badges' => [
                'new_count' => $newCount,
                'updated_count' => $updatedCount,
                'price_drop_count' => $priceDropCount,
                'back_to_market_count' => $backToMarketCount,
                'needs_update_count' => $needsUpdateCount,
            ],
            'charts' => [
                'agents_performance' => $agentsPerformance,
                'team_structure' => $teamStructure,
                'property_types' => $typesDistribution,
                'areas_distribution' => $areasDistribution,
                'market_distribution' => [
                    ['label' => 'عقارات جديدة', 'count' => $newCount, 'key' => 'new', 'color' => '#10b981'],
                    ['label' => 'تم تحديثها مؤخراً', 'count' => $updatedCount, 'key' => 'updated', 'color' => '#3b82f6'],
                    ['label' => 'انخفاض في الأسعار', 'count' => $priceDropCount, 'key' => 'price_drop', 'color' => '#f59e0b'],
                    ['label' => 'عادت للسوق', 'count' => $backToMarketCount, 'key' => 'back_to_market', 'color' => '#8b5cf6'],
                ],
            ],
        ]);
    }
}
