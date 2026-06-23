<?php

namespace App\DataTables;

use App\Helper;
use App\Models\Report\Report;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ReportDataTable extends DataTable {
    public function dataTable( $query ) {
        return datatables()
            ->eloquent($query)
            ->editColumn('publish_date_time', function ( $report ) {
                return $report->publish_date_time;
            })
            ->addColumn('download_link', function ( $report ) {
                $data = $report->downloaded_file_path;

                $zipPath      = $data->zip ?? null;
                $htmlFileName = ( is_string($v = $data->html ?? null) ? [ $v ] : array_values((array) $v) )[0] ?? null;

                if ( $zipPath
                     && Storage::disk()
                         ->exists($zipPath) ) {
                    $fileExistsInsideZip = true;
                    if ( $htmlFileName ) {
                        $fileExistsInsideZip = app(Helper::class)->fileExistsInZip($zipPath, $htmlFileName);
                    }

                    if ( !$fileExistsInsideZip ) {
                        // فایل داخل زیپ یافت نشد: نمایش پیغام + دکمه دریافت (در صورت وجود URL)
                        $output = '<span class="badge bg-warning text-dark">فایل داخل زیپ یافت نشد</span>';
                        if ( $report->url ) {
                            $output .= ' <button class="btn btn-outline-purple btn-sm download-single-btn" data-id="' . $report->id . '"><i class="fas fa-download"></i> دریافت</button>';
                        }
                        return $output;
                    }

                    // حالت موفق: دکمه مشاهده
                    $url       = route('reports.view', $report->id);
                    $fileBadge = $htmlFileName ? '<span class="badge bg-light text-dark ms-1" style="direction: ltr" title="نام فایل داخل ZIP">' . e($htmlFileName) . '</span>' : '';
                    return '<a href="' . $url . '" class="btn btn-purple btn-sm" target="_blank"><i class="fas fa-eye"></i> مشاهده</a>' . $fileBadge;
                }

                // اگر فایل ZIP وجود ندارد ولی URL اصلی دارد: دکمه دریافت
                if ( $report->url ) {
                    return '<button class="btn btn-outline-purple btn-sm download-single-btn" data-id="' . $report->id . '"><i class="fas fa-download"></i> دریافت</button>';
                }

                return '<span class="badge bg-secondary">بدون فایل</span>';
            })
            ->rawColumns([ 'download_link' ]);
    }

    public function query( Report $model ) {
        return $model->newQuery()
            ->with('stock');
    }

    public function html() {
        return $this->builder()
            ->setTableId('reports-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->responsive(true)
            ->orderBy(5);
    }

    protected function getColumns() {
        return [
            Column::make('id')
                ->title('شناسه')
                ->width(50),
            Column::make('tracing_no')
                ->title('شماره پیگیری')
                ->width(100),
            Column::make('symbol')
                ->title('نماد')
                ->width(80),
            Column::make('company_name')
                ->title('نام شرکت'),
            Column::make('title')
                ->title('عنوان گزارش'),
            Column::make('publish_date_time')
                ->title('تاریخ انتشار'),
            Column::computed('download_link')
                ->title('فایل')
                ->orderable(false)
                ->width(120)
                ->searchable(false),
        ];
    }
}