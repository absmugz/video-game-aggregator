<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GamesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $before = Carbon::now()->subMonths(2)->timestamp;
        $after = Carbon::now()->addMonths(2)->timestamp;
        $current  = Carbon::now()->timestamp;
        $afterFourMonths = Carbon::now()->addMonths(4)->timestamp;

        $popularGames = Http::withHeaders(config('services.igdb'))->withOptions([
                        'body' => "
                        fields name, cover.url, first_release_date, popularity, platforms.abbreviation, rating;
                        where platforms = (48,49,130,6)
                        & (first_release_date > {$before}
                        & first_release_date < {$after});
                        sort popularity desc;
                        limit 12;
                        "
                    ])->get('https://api-v3.igdb.com/games/')
                    ->json();

        $recentlyReviewed = Http::withHeaders(config('services.igdb'))->withOptions([
                        'body' => "
                        fields name, cover.url, first_release_date, popularity, platforms.abbreviation, rating, rating_count, summary;
                        where platforms = (48,49,130,6)
                        & (first_release_date > {$before}
                        & first_release_date < {$current}
                        & rating_count > 5);
                        sort popularity desc;
                        limit 3;
                        "
                    ])->get('https://api-v3.igdb.com/games/')
                    ->json();

        $mostAnticipated = Http::withHeaders(config('services.igdb'))->withOptions([
                        'body' => "
                        fields name, cover.url, first_release_date, popularity, platforms.abbreviation, rating, rating_count, summary;
                        where platforms = (48,49,130,6)
                        & (first_release_date > {$before}
                        & first_release_date < {$afterFourMonths});
                        sort popularity desc;
                        limit 4;
                        "
                    ])->get('https://api-v3.igdb.com/games/')
                    ->json();

        $comingSoon = Http::withHeaders(config('services.igdb'))->withOptions([
                        'body' => "
                        fields name, cover.url, first_release_date, popularity, platforms.abbreviation, rating, rating_count, summary;
                        where platforms = (48,49,130,6)
                        & (first_release_date > {$current}
                        & popularity > 5);
                        sort first_release_date asc;
                        limit 4;
                        "
                    ])->get('https://api-v3.igdb.com/games/')
                    ->json();
        
        // dump($comingSoon);

        // return view('index', [
            // 'popularGames' => $popularGames,
            //'recentlyReviewed' => $recentlyReviewed,
            // 'mostAnticipated' => $mostAnticipated,
            // 'comingSoon' => $comingSoon,
       // ]);
       return view('index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

  /**
     * Display the specified resource.
     *
     * @param  string  $slug
     * @return \Illuminate\Http\Response
     */
    public function show($slug)
    {
        $game = Http::withHeaders(config('services.igdb'))
            ->withOptions([
                'body' => "
                    fields name, cover.url, first_release_date, popularity, platforms.abbreviation, rating,
                    slug, involved_companies.company.name, genres.name, aggregated_rating, summary, websites.*, videos.*, screenshots.*, similar_games.cover.url, similar_games.name, similar_games.rating,similar_games.platforms.abbreviation, similar_games.slug;
                    where slug=\"{$slug}\";
                "
            ])->get('https://api-v3.igdb.com/games')
            ->json();

        abort_if(!$game, 404);

        return view('show', [
            'game' => $this->formatGameForView($game[0]),
        ]);
    }

    private function formatGameForView($game)
    {
        return collect($game)->merge([
            'coverImageUrl' => Str::replaceFirst('thumb', 'cover_big', $game['cover']['url']),
            'genres' => collect($game['genres'])->pluck('name')->implode(', '),
            'involvedCompanies' => $game['involved_companies'][0]['company']['name'],
            'platforms' => collect($game['platforms'])->pluck('abbreviation')->implode(', '),
            'memberRating' => array_key_exists('rating', $game) ? round($game['rating']).'%' : '0%',
            'criticRating' => array_key_exists('aggregated_rating', $game) ? round($game['aggregated_rating']).'%' : '0%',
            'trailer' => 'https://youtube.com/watch/'.$game['videos'][0]['video_id'],
            'screenshots' => collect($game['screenshots'])->map(function ($screenshot) {
                return [
                    'big' => Str::replaceFirst('thumb', 'screenshot_big', $screenshot['url']),
                    'huge' => Str::replaceFirst('thumb', 'screenshot_huge', $screenshot['url']),
                ];
            })->take(9),
            'similarGames' => collect($game['similar_games'])->map(function ($game) {
                return collect($game)->merge([
                    'coverImageUrl' => array_key_exists('cover', $game)
                        ? Str::replaceFirst('thumb','cover_big', $game['cover']['url'])
                        : 'https://via.placeholder.com/264x352',
                    'rating' => isset($game['rating']) ? round($game['rating']).'%' : null,
                    'platforms' => array_key_exists('platforms', $game)
                        ? collect($game['platforms'])->pluck('abbreviation')->implode(', ')
                        : null,
                ]);
            })->take(6),
            'social' => [
                'website' => collect($game['websites'])->first(),
                'facebook' => collect($game['websites'])->filter(function ($website) {
                    return Str::contains($website['url'], 'facebook');
                })->first(),
                'twitter' => collect($game['websites'])->filter(function ($website) {
                    return Str::contains($website['url'], 'twitter');
                })->first(),
                'instagram' => collect($game['websites'])->filter(function ($website) {
                    return Str::contains($website['url'], 'instagram');
                })->first(),
            ]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
