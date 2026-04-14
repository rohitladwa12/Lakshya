<?php
/**
 * LearningChapter Model
 */
class LearningChapter extends Model {
    protected $table = 'learning_chapters';
    protected $fillable = ['title', 'description', 'content', 'display_order', 'is_active'];
}
