<?php
/**
 * LearningModule Model
 */
class LearningModule extends Model {
    protected $table = 'learning_modules';
    protected $fillable = ['chapter_id', 'title', 'description', 'video_url', 'pdf_url', 'content', 'display_order', 'is_active'];
}
