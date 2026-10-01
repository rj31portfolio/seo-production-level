<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Website;

return [
    'clients' => ['model' => Client::class, 'label' => 'Clients', 'singular' => 'Client', 'description' => 'Manage client relationships and agency records.', 'fields' => [
        'name' => ['Name', 'text', 'required|string|max:255'], 'company' => ['Company', 'text', 'nullable|string|max:255'], 'email' => ['Email', 'email', 'nullable|email|max:255'], 'phone' => ['Phone', 'text', 'nullable|string|max:40'], 'whatsapp' => ['WhatsApp', 'text', 'nullable|string|max:40'], 'website' => ['Website', 'url', 'nullable|url:http,https|max:2048'], 'industry' => ['Industry', 'text', 'nullable|string|max:255'], 'country' => ['Country', 'text', 'nullable|string|max:255'], 'state' => ['State', 'text', 'nullable|string|max:255'], 'city' => ['City', 'text', 'nullable|string|max:255'], 'target_locations' => ['Target locations', 'textarea', 'nullable|string|max:5000'], 'notes' => ['Notes', 'textarea', 'nullable|string|max:20000'], 'status' => ['Status', 'status', 'required|in:active,paused,completed,archived']]],
    'websites' => ['model' => Website::class, 'label' => 'Websites', 'singular' => 'Website', 'description' => 'Organize the websites your agency manages.', 'fields' => [
        'client_id' => ['Client', 'client', 'required|integer'], 'name' => ['Website name', 'text', 'required|string|max:255'], 'url' => ['Website URL', 'url', 'required|url:http,https|max:2048'], 'cms' => ['CMS', 'text', 'nullable|string|max:255'], 'hosting' => ['Hosting', 'text', 'nullable|string|max:255'], 'industry' => ['Industry', 'text', 'nullable|string|max:255'], 'country' => ['Country', 'text', 'nullable|string|max:255'], 'target_locations' => ['Target locations', 'textarea', 'nullable|string|max:5000'], 'status' => ['Status', 'status', 'required|in:active,paused,completed,archived']]],
    'projects' => ['model' => Project::class, 'label' => 'Projects', 'singular' => 'Project', 'description' => 'Coordinate your SEO projects, strategy, and assigned team.', 'fields' => [
        'client_id' => ['Client', 'client', 'required|integer'], 'website_id' => ['Website', 'website', 'required|integer'], 'name' => ['Project name', 'text', 'required|string|max:255'], 'type' => ['Project type', 'text', 'required|string|max:100'], 'start_date' => ['Start date', 'date', 'required|date'], 'target_locations' => ['Target locations', 'textarea', 'nullable|string|max:5000'], 'strategy' => ['SEO strategy', 'textarea', 'nullable|string|max:20000'], 'goals' => ['Goals', 'textarea', 'nullable|string|max:20000'], 'notes' => ['Notes', 'textarea', 'nullable|string|max:20000'], 'status' => ['Status', 'status', 'required|in:active,paused,completed,archived']]],
];
