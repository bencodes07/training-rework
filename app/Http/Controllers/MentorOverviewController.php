<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MentorOverviewController extends Controller
{

    public function __construct()
    {
        
    }

    /**
     * Show all familiarisations
     */
    public function index(Request $request): Response
    {
        try {
            return Inertia::render('training/mentor-overview');
        } catch (\Exception $e) {
            \Log::error('Error loading familiarisations', [
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('training/familiarisations', [
                'familiarisations' => [],
                'error' => 'Failed to load familiarisations.',
            ]);
        }
    }
  }