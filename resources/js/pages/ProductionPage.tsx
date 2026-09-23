import React from 'react';
import { PageHeader } from '../components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../components/ui/Card';
import { Badge } from '../components/ui/Badge';
import { Mic, Film, Sparkles } from 'lucide-react';

export const ProductionPage: React.FC = () => {
  return (
    <div>
      <PageHeader
        title="Production Studio"
        description="Assemble TTS voiceovers, visual assets, subtitle sync, and render YouTube Shorts."
        badge={<Badge variant="violet">Phase 3 & 4</Badge>}
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card variant="subtle">
          <CardHeader>
            <div className="flex items-center gap-3 mb-2">
              <div className="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                <Mic className="w-5 h-5" />
              </div>
              <CardTitle>TTS Voice Generation</CardTitle>
            </div>
            <CardDescription>
              Synthesize natural voiceovers with custom pitch, rate, and voice presets.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <span className="text-xs text-slate-400 font-medium">Status: Planned in Phase 3</span>
          </CardContent>
        </Card>

        <Card variant="subtle">
          <CardHeader>
            <div className="flex items-center gap-3 mb-2">
              <div className="p-2 rounded-lg bg-violet-500/10 text-violet-400">
                <Film className="w-5 h-5" />
              </div>
              <CardTitle>FFmpeg Rendering</CardTitle>
            </div>
            <CardDescription>
              Automated multi-layer video assembly with dynamic subtitles & transition effects.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <span className="text-xs text-slate-400 font-medium">Status: Planned in Phase 4</span>
          </CardContent>
        </Card>

        <Card variant="subtle">
          <CardHeader>
            <div className="flex items-center gap-3 mb-2">
              <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-400">
                <Sparkles className="w-5 h-5" />
              </div>
              <CardTitle>Human Review & Approval</CardTitle>
            </div>
            <CardDescription>
              Human-in-the-loop quality review before publishing to YouTube.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <span className="text-xs text-slate-400 font-medium">Status: Planned in Phase 5</span>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};
