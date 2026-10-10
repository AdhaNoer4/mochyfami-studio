import React from 'react';
import { Card } from '../ui/Card';
import { Button } from '../ui/Button';
import { AlertTriangle } from 'lucide-react';

export interface AudioConfirmDialogProps {
  title: string;
  message: React.ReactNode;
  confirmLabel: string;
  busy: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}

/**
 * A small, task-specific confirmation dialog used where an action is worth a
 * second look but has no parameters to collect.
 */
export const AudioConfirmDialog: React.FC<AudioConfirmDialogProps> = ({
  title,
  message,
  confirmLabel,
  busy,
  onCancel,
  onConfirm,
}) => {
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl my-8">
        <div className="flex items-start gap-3 mb-4">
          <div className="flex items-center justify-center w-8 h-8 rounded-full bg-rose-500/10 border border-rose-500/30 shrink-0">
            <AlertTriangle className="w-4 h-4 text-rose-400" />
          </div>
          <div>
            <h3 className="text-base font-bold text-white">{title}</h3>
            <div className="text-[11px] text-slate-400 mt-0.5 leading-relaxed">{message}</div>
          </div>
        </div>

        <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-800/60">
          <Button type="button" variant="outline" size="sm" disabled={busy} onClick={onCancel}>
            Batal
          </Button>
          <Button type="button" variant="danger" size="sm" isLoading={busy} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </div>
      </Card>
    </div>
  );
};