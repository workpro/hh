<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Partner;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jenssegers\Date\Date;

class News extends Model
{
    use DefaultModel, SoftDeletes;

    protected $appends = [
        'name',
        'brief_description',
        'description',
    ];

    /**
     * ----------------------------------------------------------
     * The attributes that are mass assignable.
     * ----------------------------------------------------------
     * Атрибуты, которые могут быть присвоены в массовом порядке
     * ----------------------------------------------------------
     *
     * @var array
     */
    protected $fillable = [
        'id',                   // ID
        'photo',                // Фото
        'name_old',             // Название
        'url',                  // Урл
        'date',                 // Дата
        'brief_description_old',// Краткое описание
        'description_old',      // Полное описание
        'is_partner',           // Новость партнера
        'is_active',            // Статус
    ];

    protected $with = [
        'entities',
    ];

    protected $dates = [
        'date',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    /**
     * The "booting" method of the model.
     */
    protected static function boot()
    {
        parent::boot();
    }

    // Локали
    public function entities()
    {
        return $this
            ->hasMany(NewsI18n::class, 'entity_id', 'id')
        ;
    }

    public function current_locale()
    {
        return $this->entities ? $this->entities->where('locale', get_current_locale())->first() : null;
    }

    public function getNameAttribute()
    {
        return $this->current_locale() ? $this->current_locale()->name : null;
    }

    public function getBriefDescriptionAttribute()
    {
        return $this->current_locale() ? $this->current_locale()->brief_description : null;
    }

    public function getDescriptionAttribute()
    {
        return $this->current_locale() ? $this->current_locale()->description : null;
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'news_category');
    }

    public function getCategoriesIdAttribute()
    {
        return $this->categories->isNotEmpty() ? $this->categories->pluck('id')->toArray() : [];
    }

    public function partners()
    {
        return $this->belongsToMany(Partner::class, 'news_partner');
    }

    public function getPartnersIdAttribute()
    {
        return $this->partners->isNotEmpty() ? $this->partners->pluck('id')->toArray() : [];
    }

    public function getDateFullAttribute($date)
    {
        return Carbon::parse($this->date)->format('d / m / Y');
    }

    public function setDateAttribute($date)
    {
        $this->attributes['date'] = Carbon::parse($date);
    }

    public function getDayDateAttribute($date)
    {
        return Carbon::parse($this->date)->format('d');
    }

    public function getMonthDateAttribute($date)
    {
        $locales = [
            'ru' => 'ru_RU.UTF-8',
            'en' => 'en_US.UTF-8',
        ];
        setlocale(LC_TIME, $locales[get_current_locale()]);
//        Carbon::setLocale(get_current_locale());
        return Carbon::parseFromLocale($this->date, get_current_locale())->formatLocalized('%B');
    }

    public function getFullUrlAttribute()
    {
        return path_with_locale(get_current_locale(), "/news/read/{$this->id}");
    }

}
