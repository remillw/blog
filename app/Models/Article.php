<?php

namespace App\Models;

use App\Traits\HasWebhooks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory, HasWebhooks;

    protected $fillable = [
        'site_id',
        'language_code',
        'user_id',
        'title',
        'slug',
        'content',
        'content_html',
        'excerpt',
        'cover_image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'status',
        'published_at',
        'scheduled_at',
        'is_featured',
        'reading_time',
        'word_count',
        'author_name',
        'author_bio',
        'og_title',
        'og_description',
        'og_image',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'schema_markup',
        'source',
        'external_id',
        'webhook_sent_at',
        'webhook_received_at',
        'webhook_data',
        'is_synced',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'webhook_sent_at' => 'datetime',
        'webhook_received_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_synced' => 'boolean',
        'meta_keywords' => 'array',
        'schema_markup' => 'array',
        'webhook_data' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
            if (empty($article->meta_title)) {
                $article->meta_title = $article->title;
            }
            if (empty($article->meta_description)) {
                $contentForMeta = $article->content_html ?: $article->content;
                $article->meta_description = Str::limit(strip_tags($contentForMeta), 160);
            }
            if (empty($article->og_title)) {
                $article->og_title = $article->title;
            }
            if (empty($article->og_description)) {
                $article->og_description = $article->meta_description;
            }
            if (empty($article->twitter_title)) {
                $article->twitter_title = $article->title;
            }
            if (empty($article->twitter_description)) {
                $article->twitter_description = $article->meta_description;
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withTimestamps();
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'deliverable_id')
            ->where('deliverable_type', self::class);
    }

    public function getReadingTimeAttribute(): int
    {
        if ($this->word_count) {
            return ceil($this->word_count / 200); // Assuming 200 words per minute
        }
        return 0;
    }

    public function getWordCountAttribute(): int
    {
        $contentForCount = $this->content_html ?: $this->content;
        return str_word_count(strip_tags($contentForCount));
    }
} 