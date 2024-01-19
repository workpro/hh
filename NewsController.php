<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\News;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsController extends CommonController
{
    protected $entity = 'news';

    protected $total = 6;

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {

            $this
                ->setCollect([
                    'titleIndex' => __('app.news.news'),
                ]);

            return $next($request);
        });
    }

    public function __invoke(Request $request, $url = null, News $model)
    {
        $news = $model->where('is_active', 1);

        if($request->filled('category')) {

            $ids = DB::table('news_category')
                ->where('category_id', $request->input('category'))
                ->get()
                ->pluck('news_id')
                ->toArray();

            $news = $news->whereIn('id', $ids);
        }

        if($request->filled('partner')) {

            $ids = DB::table('news_partner')
                ->where('partner_id', $request->input('partner'))
                ->get()
                ->pluck('news_id')
                ->toArray();

            $news = $news
                ->whereIn('id', $ids)
                ->where('is_partner', 1)
            ;
        } elseif ($url == 'products') {

            $news = $news->where('is_partner', 1);
            $this
                ->setCollect(['titleIndex' => __('app.news.news_partners')])
            ;
        } else {

            $news = $news->where('is_partner', 0);
            $this
                ->setCollect(['titleIndex' => __('app.news.news_company')])
            ;
        }

        $news = $news->orderBy('date', 'desc')->paginate($this->total);

        $categories = Category::where('is_active', 1)
            ->where('parent_code_1c', '')
            ->get()
            ->pluck('name', 'id');

        $partners = Partner::where('is_active', 1)
            ->where('is_main', 1)
            ->get()
            ->pluck('name', 'id');

        $breadcrumbs = array_merge($this->getCollect('breadcrumbs'), [
            [
                'url' => path_with_locale(get_current_locale(), $url ? "/news/{$url}" : "/news"),
                'name' => $this->getCollect('titleIndex'),
            ],
        ]);

        $this
            ->setCollect('news', $news->onEachSide(0))
            ->setCollect('categoryId', $request->input('category'))
            ->setCollect('categories', $categories)
            ->setCollect('partnerId', $request->input('partner'))
            ->setCollect('partners', $partners)
            ->setCollect('breadcrumbs', $breadcrumbs)
        ;

        return view("{$this->entity}.items", $this->getCollect());
    }

    public function read(Request $request, $id)
    {
        if (Str::contains($id, ['_'])){

            $url_clear = Str::after($id, '_');
//            echo $url_clear;
            $name = str_replace('_', ' ', $url_clear);
//            echo $name;
            $news = News::whereHas('entities', function ($news) use ($name) {

                $news
                    ->where('name', $name)
                ;
            })->first();

            if (!!$news){

                return redirect(route('news.read', $news->id), 301);
            } else {

                return abort('404');
            }
        }

        $news = News::where('is_active', 1)
            ->where('id', $id)
            ->first()
        ;

        if (!!$news) {

            $breadcrumbs = array_merge($this->getCollect('breadcrumbs'), [
                [
                    'url' => path_with_locale(get_current_locale(), "/news"),
                    'name' => __('app.news.news_company'),
                ],
                [
                    'url' => path_with_locale(get_current_locale(), "/news/read/{$news->id}"),
                    'name' => $news->name,
                ],
            ]);

            $newsCompany = News::where('is_partner', 0)
                ->where('is_active', 1)
                ->orderBy('date', 'desc')
                ->take(2)
                ->get()
            ;
            $newsPartner = News::where('is_partner', 1)
                ->where('is_active', 1)
                ->orderBy('date', 'desc')
                ->take(2)
                ->get()
            ;

            $this
                ->setCollect('news', $news)
                ->setCollect('newsCompany', $newsCompany)
                ->setCollect('newsPartner', $newsPartner)
                ->setCollect('breadcrumbs', $breadcrumbs)
            ;

            return view("{$this->entity}.item", $this->getCollect());
        } else {

            return abort('404');
        }
    }

}
