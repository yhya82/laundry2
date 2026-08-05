<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::with('causer')
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->get('subject_type')))
            ->when($request->filled('causer_id'), fn ($q) => $q->where('causer_id', $request->get('causer_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->get('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->get('to').' 23:59:59'))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        $subjectTypes = ActivityLog::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type');

        $causers = User::whereIn('id', ActivityLog::whereNotNull('causer_id')->distinct()->pluck('causer_id'))
            ->orderBy('name')
            ->get();

        return view('audit.index', compact('logs', 'subjectTypes', 'causers'));
    }
}
