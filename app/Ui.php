<?php
declare(strict_types=1);

namespace App;

final class Ui
{
    /**
     * @return array{
     *   field: string,
     *   controlSelect: string,
     *   btn: string,
     *   btnPrimary: string,
     *   chip: string,
     *   label: string,
     *   hint: string,
     *   result: string,
     *   resultBody: string,
     *   iconBtn: string,
     *   iconSvg: string,
     *   stat: string,
     *   check: string,
     *   controlRow: string
     * }
     */
    public static function classes(): array
    {
        return [
            'field' => 'w-full min-w-0 rounded-xl border border-line bg-panel px-3.5 py-3 text-base text-ink outline-none transition placeholder:text-muted/70 hover:border-leaf/40 focus:border-leaf/50 focus:ring-4 focus:ring-accent/25 aria-invalid:border-danger aria-invalid:ring-4 aria-invalid:ring-danger/20 sm:text-sm',
            'controlSelect' => 'h-11 min-h-11 w-full min-w-0 shrink-0 cursor-pointer appearance-none touch-manipulation rounded-xl border border-line bg-panel py-2 pl-3 pr-10 text-base text-ink shadow-sm outline-none transition-[border-color,box-shadow,background-color] duration-150 hover:border-leaf/40 focus:border-leaf/50 focus:ring-4 focus:ring-accent/25 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none sm:text-sm',
            'btn' => 'inline-flex h-11 min-h-11 w-full shrink-0 touch-manipulation cursor-pointer items-center justify-center rounded-xl border border-line bg-card px-4 text-sm font-semibold text-ink transition-[background-color,border-color,transform,opacity] duration-150 hover:border-leaf/50 hover:bg-moss/80 active:scale-[0.98] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-leaf disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none motion-reduce:active:scale-100 sm:w-auto',
            'btnPrimary' => 'inline-flex h-11 min-h-11 w-full shrink-0 touch-manipulation cursor-pointer items-center justify-center rounded-xl bg-ink px-4 text-sm font-semibold text-inverse transition-[background-color,transform,opacity] duration-150 hover:bg-ink/90 active:scale-[0.98] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-leaf disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none motion-reduce:active:scale-100 sm:w-auto',
            'chip' => 'inline-flex size-11 shrink-0 touch-manipulation cursor-pointer items-center justify-center rounded-xl border border-line bg-card text-sm font-semibold text-ink transition-[background-color,border-color,transform,opacity] duration-150 hover:border-leaf/50 hover:bg-moss/80 active:scale-[0.98] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-leaf disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none motion-reduce:active:scale-100',
            'label' => 'mb-2 block text-sm font-semibold text-ink',
            'hint' => 'mt-3 text-xs leading-relaxed text-muted',
            'result' => 'mt-4 flex flex-col items-stretch gap-3 rounded-2xl border border-line bg-soft p-4 sm:flex-row sm:items-start',
            'resultBody' => 'min-h-12 min-w-0 flex-1 overflow-x-auto overflow-y-auto whitespace-pre-wrap break-all font-mono text-sm text-ink',
            'iconBtn' => 'inline-flex size-11 touch-manipulation items-center justify-center rounded-xl text-muted transition hover:bg-card hover:text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-leaf',
            'iconSvg' => 'size-5 fill-none stroke-current [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:1.8]',
            'stat' => 'rounded-2xl border border-leaf/25 bg-moss px-4 py-4 text-left sm:px-5',
            'check' => 'inline-flex min-h-11 items-center gap-2 px-1 text-sm text-ink',
            'controlRow' => 'mt-3 flex w-full flex-col gap-2 sm:w-fit sm:flex-row sm:items-center sm:gap-2',
        ];
    }
}
