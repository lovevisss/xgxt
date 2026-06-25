<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_academic_year_averages') || ! $this->supportsColumnComments()) {
            return;
        }

        foreach ($this->columns() as $column => $definition) {
            DB::statement("ALTER TABLE `student_academic_year_averages` MODIFY COLUMN `{$column}` {$definition['type']} COMMENT '{$definition['comment']}'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_academic_year_averages') || ! $this->supportsColumnComments()) {
            return;
        }

        foreach ($this->columns() as $column => $definition) {
            DB::statement("ALTER TABLE `student_academic_year_averages` MODIFY COLUMN `{$column}` {$definition['type']} COMMENT ''");
        }
    }

    private function supportsColumnComments(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @return array<string, array{type: string, comment: string}>
     */
    private function columns(): array
    {
        return [
            'id' => ['type' => 'bigint unsigned NOT NULL AUTO_INCREMENT', 'comment' => '主键ID'],
            'student_xgh' => ['type' => 'varchar(32) NOT NULL', 'comment' => '学生学号'],
            'student_name' => ['type' => 'varchar(255) NULL', 'comment' => '学生姓名'],
            'academic_year' => ['type' => 'varchar(16) NOT NULL', 'comment' => '学年，如 2025-2026'],
            'class_code' => ['type' => 'varchar(255) NULL', 'comment' => '班级代码'],
            'class_name' => ['type' => 'varchar(255) NULL', 'comment' => '班级名称'],
            'major_code' => ['type' => 'varchar(255) NULL', 'comment' => '专业代码'],
            'average_score' => ['type' => 'decimal(6,2) NULL', 'comment' => '学年学习平均成绩'],
            'total_credits' => ['type' => 'decimal(8,2) NOT NULL DEFAULT 0.00', 'comment' => '参与计算的总学分'],
            'course_count' => ['type' => 'int unsigned NOT NULL DEFAULT 0', 'comment' => '参与计算的课程数量'],
            'class_rank' => ['type' => 'int unsigned NULL', 'comment' => '班级排名'],
            'class_size' => ['type' => 'int unsigned NULL', 'comment' => '班级参与排名人数'],
            'major_rank' => ['type' => 'int unsigned NULL', 'comment' => '专业排名'],
            'major_size' => ['type' => 'int unsigned NULL', 'comment' => '专业参与排名人数'],
            'calculated_at' => ['type' => 'timestamp NULL', 'comment' => '计算时间'],
            'created_at' => ['type' => 'timestamp NULL', 'comment' => '创建时间'],
            'updated_at' => ['type' => 'timestamp NULL', 'comment' => '更新时间'],
        ];
    }
};
