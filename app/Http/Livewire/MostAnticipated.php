<?php

namespace App\Http\Livewire;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

use Livewire\Component;

class MostAnticipated extends Component
{

    public array $mostAnticipated = []; 

    public function loadMostAnticipated()
    {

        $before = Carbon::now()->subMonths(2)->timestamp;
        $afterFourMonths = Carbon::now()->addMonths(4)->timestamp;

        $this->mostAnticipated = Http::withHeaders(config('services.igdb'))->withOptions([
            'body' => "
            fields name, cover.url, first_release_date, popularity, platforms.abbreviation, rating, rating_count, summary, slug;
            where platforms = (48,49,130,6)
            & (first_release_date > {$before}
            & first_release_date < {$afterFourMonths});
            sort popularity desc;
            limit 4;
            "
        ])->get('https://api-v3.igdb.com/games/')
        ->json();
    }
    public function render()
    {
        return view('livewire.most-anticipated');
    }
}
