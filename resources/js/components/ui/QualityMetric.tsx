type QualityMetricTone = 'emerald' | 'rose' | 'amber' | 'indigo';

const toneClasses: Record<QualityMetricTone, string> = {
  emerald: 'text-emerald-400',
  rose: 'text-rose-400',
  amber: 'text-amber-400',
  indigo: 'text-indigo-400',
};

/**
 * A single labelled number inside a quality summary grid.
 *
 * Shared by the script quality gate and the visual plan readiness gate so the
 * two panels stay visually consistent.
 */
export function QualityMetric({
  label,
  value,
  tone,
}: {
  label: string;
  value: string;
  tone: QualityMetricTone;
}) {
  return (
    <div className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
      <span className={`block text-lg font-bold ${toneClasses[tone]}`}>{value}</span>
      <span className="block text-[10px] uppercase tracking-wider text-slate-500">{label}</span>
    </div>
  );
}
