import React, { useEffect, useState } from 'react';
import { Card } from '../ui/Card';
import { Button } from '../ui/Button';
import { Loader2 } from 'lucide-react';
import { TtsGeneratePayload, VisualPlanItem } from '../../types';

export interface AudioGenerateModalProps {
  mode: 'generate' | 'regenerate';
  item: VisualPlanItem;
  busy: boolean;
  onCancel: () => void;
  onSubmit: (payload: TtsGeneratePayload) => void;
}

const inputClasses =
  'bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 w-full';

const labelClasses = 'block text-[11px] font-semibold text-slate-400 mb-1';

/**
 * The hard limits the backend DTO enforces. They are mirrored here only so the
 * user is told while typing rather than after a round trip; the server still
 * owns the decision and can refuse anything.
 */
const VOICE_MAX_LENGTH = 100;
const LANGUAGE_MAX_LENGTH = 20;
const MIN_SPEED = 0.5;
const MAX_SPEED = 2.0;

const isSpeedValid = (value: string): boolean => {
  const trimmed = value.trim();
  if (trimmed === '') {
    return true;
  }
  const parsed = Number(trimmed);
  if (Number.isNaN(parsed)) {
    return false;
  }
  return parsed >= MIN_SPEED && parsed <= MAX_SPEED;
};

/**
 * The form for one synthesis request. provider and model are deliberately not
 * offered: the backend resolves the registered default provider and an unknown
 * provider name is a 502 rather than something this form can prevent.
 */
export const AudioGenerateModal: React.FC<AudioGenerateModalProps> = ({
  mode,
  item,
  busy,
  onCancel,
  onSubmit,
}) => {
  const [voice, setVoice] = useState<string>('neutral');
  const [speed, setSpeed] = useState<string>('1.0');
  const [language, setLanguage] = useState<string>('');
  const [voiceError, setVoiceError] = useState<string | null>(null);
  const [speedError, setSpeedError] = useState<string | null>(null);

  // A fresh form per item keeps a voice typed for one item from leaking into
  // the next request without the user noticing.
  useEffect(() => {
    setVoice('neutral');
    setSpeed('1.0');
    setLanguage('');
    setVoiceError(null);
    setSpeedError(null);
  }, [item.id, mode]);

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();

    const trimmedVoice = voice.trim();

    if (trimmedVoice === '') {
      setVoiceError('Nama suara wajib diisi.');
      return;
    }

    if (!isSpeedValid(speed)) {
      setSpeedError(`Kecepatan harus antara ${MIN_SPEED} dan ${MAX_SPEED}.`);
      return;
    }

    const trimmedLanguage = language.trim();

    const payload: TtsGeneratePayload = {
      voice: trimmedVoice,
      ...(trimmedLanguage === '' ? {} : { language: trimmedLanguage }),
      ...(speed.trim() === '' ? {} : { speed: Number(speed) }),
    };

    onSubmit(payload);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      <Card variant="default" className="max-w-lg w-full p-6 border-slate-800 shadow-2xl my-8">
        <div className="flex items-center justify-between mb-5">
          <div>
            <h3 className="text-base font-bold text-white">
              {mode === 'generate' ? 'Buat Audio Narasi' : 'Buat Ulang Audio'}
            </h3>
            <p className="text-[11px] text-slate-400 mt-0.5">
              {mode === 'generate'
                ? 'Suara disintesis dari narasi item di bawah ini. Riwayat audio sebelumnya tetap tersimpan.'
                : 'Track baru akan dibuat dari narasi item saat ini. Audio lama tidak dihapus, hanya menjadi riwayat.'}
            </p>
          </div>
        </div>

        <div className="rounded-lg border border-slate-800 bg-slate-900/60 px-3 py-2 mb-4">
          <p className="text-[10px] uppercase tracking-wider text-slate-500 mb-1">
            Item #{item.order} · {item.section_label}
          </p>
          <p className="text-xs text-slate-300 line-clamp-3">{item.narration_text}</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className={labelClasses} htmlFor="tts-voice">
              Nama suara <span className="text-rose-400">*</span>
            </label>
            <input
              id="tts-voice"
              type="text"
              value={voice}
              maxLength={VOICE_MAX_LENGTH}
              onChange={(event) => {
                setVoice(event.target.value);
                setVoiceError(null);
              }}
              className={inputClasses}
              placeholder="neutral"
            />
            {voiceError ? (
              <p className="text-[10px] text-rose-400 mt-1">{voiceError}</p>
            ) : (
              <p className="text-[10px] text-slate-500 mt-1">
                Wajib diisi, maksimal {VOICE_MAX_LENGTH} karakter.
              </p>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className={labelClasses} htmlFor="tts-speed">
                Kecepatan
              </label>
              <input
                id="tts-speed"
                type="number"
                step="0.05"
                min={MIN_SPEED}
                max={MAX_SPEED}
                value={speed}
                onChange={(event) => {
                  setSpeed(event.target.value);
                  setSpeedError(null);
                }}
                className={inputClasses}
              />
              {speedError ? (
                <p className="text-[10px] text-rose-400 mt-1">{speedError}</p>
              ) : (
                <p className="text-[10px] text-slate-500 mt-1">
                  Antara {MIN_SPEED} dan {MAX_SPEED}.
                </p>
              )}
            </div>

            <div>
              <label className={labelClasses} htmlFor="tts-language">
                Bahasa (opsional)
              </label>
              <input
                id="tts-language"
                type="text"
                value={language}
                maxLength={LANGUAGE_MAX_LENGTH}
                onChange={(event) => setLanguage(event.target.value)}
                className={inputClasses}
                placeholder="id"
              />
            </div>
          </div>

          <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-800/60">
            <Button type="button" variant="outline" size="sm" disabled={busy} onClick={onCancel}>
              Batal
            </Button>
            <Button type="submit" variant="primary" size="sm" isLoading={busy}>
              {mode === 'generate' ? 'Buat Audio' : 'Buat Ulang'}
            </Button>
          </div>
        </form>

        {busy && (
          <div className="flex items-center gap-2 text-[11px] text-slate-500 mt-3 justify-end">
            <Loader2 className="w-3 h-3 animate-spin" />
            Menyintesis audio, mohon tunggu...
          </div>
        )}
      </Card>
    </div>
  );
};