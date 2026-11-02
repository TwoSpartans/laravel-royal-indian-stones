<?php

namespace App\Http\Controllers\Portal;

use App\Models\EmergencyContact;
use App\Models\EmployeeDocument;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AccountController extends PortalController
{
    public function show(Request $request): View
    {
        $actor = $this->actor($request);
        $actor->load('profile.department', 'profile.location', 'profile.shift', 'profile.manager', 'devices');

        return view('portal.account', [
            'person' => $actor,
            'contacts' => EmergencyContact::query()->where('user_id', $actor->id)->get(),
            'documents' => EmployeeDocument::query()->where('user_id', $actor->id)->orderByDesc('id')->get(),
            'payslips' => $this->publishedPayslips($actor),
        ]);
    }

    public function payslips(Request $request): View
    {
        $actor = $this->actor($request);

        return view('portal.payslips.index', [
            'payslips' => $this->publishedPayslips($actor),
        ]);
    }

    /**
     * @return Collection<int, Payslip>
     */
    private function publishedPayslips(User $actor): Collection
    {
        if (! $actor->canOpenPortalSection('payslips')) {
            return new Collection;
        }

        return Payslip::query()
            ->where('user_id', $actor->id)
            ->where('status', 'published')
            ->orderByDesc('period')
            ->get();
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $actor = $this->actor($request);
        $item = $actor->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();
        $url = $item->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, url('/portal'))
            ? redirect()->to($url)
            : redirect()->route('portal.notifications');
    }

    public function inbox(Request $request): View
    {
        $actor = $this->actor($request);

        return view('portal.notifications', [
            'notifications' => $actor->notifications()->latest()->paginate(20),
        ]);
    }
}
