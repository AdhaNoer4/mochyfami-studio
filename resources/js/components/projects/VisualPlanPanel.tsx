import React, { useEffect, useState } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { getApiErrorMessage } from '../../services/researchService';
import { scriptService } from '../../services/scriptService';
import { visualPlanService } from '../../services/visualPlanService';
import {
  Script,
  ScriptQualityResult,
  VisualPlan,
  VisualPlanItem,
  VisualPlanItemFormData,
  VisualPlanItemType,
  VisualPlanSection,
  VisualPlanStatus,
  VisualPlanTransition,
} from '../../types';
import {
  AlertCircle,
  ArrowDown,
  ArrowUp,
  CheckCircle2,
  Clapperboard,
  Clock,
  Layers,
  Lightbulb,
  Pencil,
  Plus,
  Save,
  ShieldCheck,
  Sparkles,
  Trash2,
  X,
} from 'lucide-react';

interface VisualPlanPanelProps {
  projectId: number;
}

interface ItemFormState {
  section: VisualPlanSection;
  narration_text: string;
  visual_type: VisualPlanItemType;
  visual_prompt: string;
  duration_seconds: string;
  notes: string;
}

const emptyItemForm = (): ItemFormState => ({
  section: 'body',
  narration_text: '',
  visual_type: 'stock_video',
  visual_prompt: '',
  duration_seconds: '5',
  notes: '',
});

const toItemForm = (item: VisualPlanItem): ItemFormState => ({
  section: item.section,
  narration_text: item.narration_text,
  visual_type: item.visual_type,
  visual_prompt: item.visual_prompt,
  duration_seconds: String(item.duration_seconds),
  notes: item.notes ?? '',
});

const sectionOptions: { value: VisualPlanSection; label: string }[] = [
  { value: 'hook', label: 'Hook' },
  { value: 'body', label: 'Body' },
  { value: 'closing', label: 'Closing' },
  { value: 'other', label: 'Other' },
];

const visualTypeOptions: { value: VisualPlanItemType; label: string }[] = [
  { value: 'animal_clip', label: 'Animal Clip' },
  { value: 'stock_video', label: 'Stock Video' },
  { value: 'photo', label: 'Photo' },
  { value: 'screen_recording', label: 'Screen Recording' },
  { value: 'graphic', label: 'Graphic' },
  { value: 'text', label: 'Text' },
  { value: 'b_roll', label: 'B-Roll' },
  { value: 'other', label: 'Other' },
];

const inputClasses =
  'bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 w-full';

const labelClasses = 'block text-[11px] font-semibold text-slate-400 mb-1';

function getStatusBadgeVariant(status: string) {
  switch (status) {
    case 'review':
      return 'amber';
    case 'approved':
      return 'emerald';
    case 'archived':
      return 'rose';
    default:
      return 'slate';
  }
}

export const VisualPlanPanel: React.FC<VisualPlanPanelProps> = ({ projectId }) => {
  const [script, setScript] = useState<Script | null>(null);
  const [quality, setQuality] = useState<ScriptQualityResult | null>(null);
  const [plan, setPlan] = useState<VisualPlan | null>(null);
  const [items, setItems] = useState<VisualPlanItem[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [panelError, setPanelError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [creating, setCreating] = useState<boolean>(false);
  const [transitioning, setTransitioning] = useState<VisualPlanStatus | null>(null);
  const [confirmTransition, setConfirmTransition] = useState<VisualPlanTransition | null>(null);
  const [reordering, setReordering] = useState<boolean>(false);

  const [editingItem, setEditingItem] = useState<number | 'new' | null>(null);
  const [itemForm, setItemForm] = useState<ItemFormState>(emptyItemForm());
  const [itemSaving, setItemSaving] = useState<boolean>(false);

  const version = script?.current_version?.version ?? null;
  const scriptReviewable = script?.status === 'review' || script?.status === 'approved';
  const scriptReady = script !== null && scriptReviewable && quality?.ready === true;

  const refreshItems = async (versionNumber: number) => {
    const data = await visualPlanService.listItems(projectId, versionNumber);
    setItems(data);
  };

  useEffect(() => {
    let active = true;
    setLoading(true);
    setPanelError(null);

    (async () => {
      try {
        const data = await scriptService.getScript(projectId);
        if (!active) return;
        setScript(data);

        if (!data?.current_version) {
          setPlan(null);
          setItems([]);
          setQuality(null);
          return;
        }

        const currentVersion = data.current_version.version;
        const [planData, qualityData] = await Promise.all([
          visualPlanService.getPlan(projectId, currentVersion),
          scriptService.getQuality(projectId).catch(() => null),
        ]);
        if (!active) return;

        setPlan(planData);
        setQuality(qualityData);
        setItems(planData ? await visualPlanService.listItems(projectId, currentVersion) : []);
      } catch (err) {
        if (active) {
          setPanelError(getApiErrorMessage(err, 'Unable to load the visual plan.'));
        }
      } finally {
        if (active) setLoading(false);
      }
    })();

    return () => {
      active = false;
    };
  }, [projectId]);

  const handleCreatePlan = async (createFromScript: boolean) => {
    if (version === null) return;
    setCreating(true);
    setMessage(null);
    setPanelError(null);
    try {
      const created = await visualPlanService.createPlan(projectId, version, {
        create_from_script: createFromScript,
      });
      setPlan(created);
      setItems(await visualPlanService.listItems(projectId, version));
      setMessage(
        createFromScript
          ? 'Visual plan created from the script. Review each seeded item.'
          : 'Visual plan created successfully.',
      );
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to create the visual plan.'));
    } finally {
      setCreating(false);
    }
  };

  const handleTransition = async (target: VisualPlanStatus) => {
    if (version === null) return;
    setTransitioning(target);
    setMessage(null);
    setPanelError(null);
    try {
      setPlan(await visualPlanService.transitionStatus(projectId, version, target));
      setConfirmTransition(null);
      setMessage('Visual plan status updated successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to update visual plan status.'));
    } finally {
      setTransitioning(null);
    }
  };

  const openNewItem = () => {
    setItemForm(emptyItemForm());
    setEditingItem('new');
  };

  const openEditItem = (item: VisualPlanItem) => {
    setItemForm(toItemForm(item));
    setEditingItem(item.id);
  };

  const closeItemForm = () => {
    setEditingItem(null);
    setPanelError(null);
  };

  const handleSaveItem = async () => {
    if (version === null) return;
    setItemSaving(true);
    setMessage(null);
    setPanelError(null);
    try {
      const payload: VisualPlanItemFormData = {
        section: itemForm.section,
        narration_text: itemForm.narration_text.trim(),
        visual_type: itemForm.visual_type,
        visual_prompt: itemForm.visual_prompt.trim(),
        duration_seconds: Number(itemForm.duration_seconds),
        notes: itemForm.notes.trim() || null,
      };

      if (editingItem === 'new') {
        await visualPlanService.createItem(projectId, version, payload);
        setMessage('Visual plan item created successfully.');
      } else if (editingItem !== null) {
        await visualPlanService.updateItem(projectId, version, editingItem, payload);
        setMessage('Visual plan item updated successfully.');
      }

      setEditingItem(null);
      await refreshItems(version);
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to save the visual plan item.'));
    } finally {
      setItemSaving(false);
    }
  };

  const handleDeleteItem = async (item: VisualPlanItem) => {
    if (version === null) return;
    setMessage(null);
    setPanelError(null);
    try {
      await visualPlanService.deleteItem(projectId, version, item.id);
      await refreshItems(version);
      setMessage('Visual plan item deleted successfully.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to delete the visual plan item.'));
    }
  };

  const handleMove = async (index: number, offset: number) => {
    if (version === null) return;
    const target = index + offset;
    if (target < 0 || target >= items.length) return;

    const reordered = [...items];
    [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
    const payload = reordered.map((item, position) => ({ id: item.id, order: position + 1 }));

    setReordering(true);
    setMessage(null);
    setPanelError(null);
    try {
      await visualPlanService.reorderItems(projectId, version, payload);
      await refreshItems(version);
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to reorder the visual plan items.'));
    } finally {
      setReordering(false);
    }
  };

  if (loading) {
    return (
      <div className="space-y-4">
        <Card variant="default">
          <CardContent className="space-y-3">
            <div className="h-14 bg-slate-900 rounded-xl animate-pulse" />
            <div className="h-24 bg-slate-900 rounded-xl animate-pulse" />
          </CardContent>
        </Card>
      </div>
    );
  }

  const renderItemForm = (mode: 'create' | 'edit', item?: VisualPlanItem) => (
    <Card variant="default" className="border-indigo-500/30">
      <CardHeader>
        <CardTitle className="text-sm font-bold text-white flex items-center gap-2">
          {mode === 'create' ? (
            <Plus className="w-4 h-4 text-indigo-400" />
          ) : (
            <Pencil className="w-4 h-4 text-indigo-400" />
          )}
          {mode === 'create' ? 'New Visual Plan Item' : `Edit Item ${item?.order}`}
        </CardTitle>
        <CardDescription>
          Each item pairs one narration line with the visual that must cover it.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label className={labelClasses} htmlFor="visual-plan-section">
              Section
            </label>
            <select
              id="visual-plan-section"
              className={inputClasses}
              value={itemForm.section}
              onChange={(e) => setItemForm({ ...itemForm, section: e.target.value as VisualPlanSection })}
            >
              {sectionOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className={labelClasses} htmlFor="visual-plan-type">
              Visual Type
            </label>
            <select
              id="visual-plan-type"
              className={inputClasses}
              value={itemForm.visual_type}
              onChange={(e) => setItemForm({ ...itemForm, visual_type: e.target.value as VisualPlanItemType })}
            >
              {visualTypeOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className={labelClasses} htmlFor="visual-plan-duration">
              Duration (seconds)
            </label>
            <input
              id="visual-plan-duration"
              type="number"
              min={1}
              max={60}
              className={inputClasses}
              value={itemForm.duration_seconds}
              onChange={(e) => setItemForm({ ...itemForm, duration_seconds: e.target.value })}
            />
          </div>
        </div>

        <div>
          <label className={labelClasses} htmlFor="visual-plan-narration">
            Narration Text
          </label>
          <textarea
            id="visual-plan-narration"
            rows={3}
            className={inputClasses}
            value={itemForm.narration_text}
            onChange={(e) => setItemForm({ ...itemForm, narration_text: e.target.value })}
            placeholder="The narration line this visual must cover"
          />
        </div>

        <div>
          <label className={labelClasses} htmlFor="visual-plan-prompt">
            Visual Prompt
          </label>
          <textarea
            id="visual-plan-prompt"
            rows={3}
            className={inputClasses}
            value={itemForm.visual_prompt}
            onChange={(e) => setItemForm({ ...itemForm, visual_prompt: e.target.value })}
            placeholder="What should be shown on screen for this narration"
          />
        </div>

        <div>
          <label className={labelClasses} htmlFor="visual-plan-notes">
            Notes
          </label>
          <input
            id="visual-plan-notes"
            className={inputClasses}
            value={itemForm.notes}
            onChange={(e) => setItemForm({ ...itemForm, notes: e.target.value })}
            placeholder="Optional production notes for this item"
          />
        </div>

        <div className="flex items-center justify-end gap-3">
          <Button variant="outline" size="sm" icon={<X className="w-3.5 h-3.5" />} onClick={closeItemForm}>
            Cancel
          </Button>
          <Button
            size="sm"
            icon={<Save className="w-3.5 h-3.5" />}
            isLoading={itemSaving}
            onClick={handleSaveItem}
          >
            Save Item
          </Button>
        </div>
      </CardContent>
    </Card>
  );

  const renderReadiness = () => {
    if (scriptReady) {
      return (
        <div className="flex items-center gap-2">
          <CheckCircle2 className="w-5 h-5 text-emerald-400" />
          <span className="text-xs font-semibold text-slate-300">Script:</span>
          <Badge variant="emerald">READY</Badge>
        </div>
      );
    }

    return (
      <div className="flex items-center gap-2">
        <AlertCircle className="w-5 h-5 text-amber-400" />
        <span className="text-xs font-semibold text-slate-300">Script:</span>
        <Badge variant="amber">NOT READY</Badge>
      </div>
    );
  };

  const renderGateNote = () => {
    if (scriptReady) {
      return 'A visual plan is bound to one exact script version.';
    }
    if (!script) {
      return 'A visual plan requires an existing script.';
    }
    if (!scriptReviewable) {
      return 'Move the script to review or approved before creating a visual plan.';
    }
    if (quality && quality.blockers.length > 0) {
      return `Fix the script quality blockers first: ${quality.blockers.map((blocker) => blocker.code).join(', ')}.`;
    }
    return 'A visual plan requires the script quality gate to pass.';
  };

  return (
    <div className="space-y-6">
      {message && (
        <div className="flex items-start gap-2 p-3 rounded-lg bg-emerald-950/40 border border-emerald-800/50 text-emerald-300 text-xs">
          <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5" />
          <span>{message}</span>
        </div>
      )}

      {panelError && (
        <div className="flex items-start gap-2 p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-300 text-xs">
          <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
          <span>{panelError}</span>
        </div>
      )}

      {/* READINESS INDICATOR */}
      <Card variant="subtle" className="border-slate-800">
        <CardContent className="p-4 space-y-2">
          <div className="flex items-center justify-between gap-3 flex-wrap">
            {renderReadiness()}
            {version !== null && (
              <span className="text-xs text-slate-500 flex items-center gap-1.5">
                <Layers className="w-3.5 h-3.5" />
                Script Version: v{version}
              </span>
            )}
          </div>
          <p className="text-[11px] text-slate-500">{renderGateNote()}</p>
        </CardContent>
      </Card>

      {!script || version === null ? (
        <Card variant="subtle" className="border-slate-800 p-10 text-center">
          <div className="flex flex-col items-center justify-center gap-4">
            <Lightbulb className="w-10 h-10 text-indigo-400" />
            <div>
              <p className="text-sm font-semibold text-white">No script to plan yet</p>
              <p className="text-xs text-slate-400 mt-1">
                Write a script first; the visual plan is built on an exact script version.
              </p>
            </div>
          </div>
        </Card>
      ) : !plan ? (
        <Card variant="subtle" className="border-slate-800 p-10 text-center">
          <div className="flex flex-col items-center justify-center gap-4">
            <Clapperboard className="w-10 h-10 text-indigo-400" />
            <div>
              <p className="text-sm font-semibold text-white">No visual plan for v{version}</p>
              <p className="text-xs text-slate-400 mt-1">
                Create an empty plan and add items manually, or seed it from the script sections.
              </p>
            </div>
            <div className="flex flex-wrap items-center justify-center gap-2">
              <Button
                size="sm"
                icon={<Plus className="w-3.5 h-3.5" />}
                isLoading={creating}
                disabled={!scriptReady}
                onClick={() => handleCreatePlan(false)}
              >
                Create Visual Plan
              </Button>
              <Button
                variant="secondary"
                size="sm"
                icon={<Sparkles className="w-3.5 h-3.5" />}
                isLoading={creating}
                disabled={!scriptReady}
                onClick={() => handleCreatePlan(true)}
              >
                Create Plan from Script
              </Button>
            </div>
          </div>
        </Card>
      ) : (
        <>
          {/* PLAN STATUS */}
          <Card variant="default">
            <CardHeader>
              <div className="flex items-center justify-between gap-3 flex-wrap">
                <div>
                  <CardTitle className="text-base font-bold text-white flex items-center gap-2">
                    <Clapperboard className="w-4 h-4 text-indigo-400" />
                    Visual Plan
                    <Badge variant="indigo">v{version}</Badge>
                    <Badge variant={getStatusBadgeVariant(plan.status)}>{plan.status_label}</Badge>
                  </CardTitle>
                  <CardDescription>
                    A plan stays bound to script v{version}. Revising the script later does not change this
                    plan.
                  </CardDescription>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  icon={<Plus className="w-3.5 h-3.5" />}
                  onClick={openNewItem}
                  disabled={editingItem !== null}
                >
                  Add Item
                </Button>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              {plan.allowed_transitions.length > 0 ? (
                <div className="flex flex-wrap items-center gap-2">
                  {plan.allowed_transitions.map((transition) => (
                    <Button
                      key={transition.status}
                      variant={transition.destructive ? 'danger' : 'secondary'}
                      size="sm"
                      icon={
                        transition.destructive ? (
                          <Trash2 className="w-3.5 h-3.5" />
                        ) : (
                          <CheckCircle2 className="w-3.5 h-3.5" />
                        )
                      }
                      isLoading={transitioning === transition.status}
                      disabled={transitioning !== null}
                      onClick={() => {
                        if (transition.destructive) {
                          setConfirmTransition(transition);
                        } else {
                          handleTransition(transition.status);
                        }
                      }}
                    >
                      {transition.action}
                    </Button>
                  ))}
                </div>
              ) : (
                <p className="text-xs text-slate-500">
                  No transitions are available from the {plan.status_label.toLowerCase()} status.
                </p>
              )}
            </CardContent>
          </Card>

          {/* ITEM FORM */}
          {editingItem === 'new' && renderItemForm('create')}

          {/* ITEMS */}
          <Card variant="default">
            <CardHeader>
              <CardTitle className="text-base font-bold text-white flex items-center gap-2">
                <Lightbulb className="w-4 h-4 text-indigo-400" />
                Plan Items
                <Badge variant="slate">{items.length}</Badge>
              </CardTitle>
              <CardDescription>
                Reorder items to match the final edit. Order is stored explicitly and stays deterministic.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
              {items.length === 0 ? (
                <p className="text-xs text-slate-500">
                  No items yet. Add the first item to start storyboarding.
                </p>
              ) : (
                items.map((item, index) => (
                  <div
                    key={item.id}
                    className="rounded-lg border border-slate-800 bg-slate-950 px-3 py-2.5 space-y-2"
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="text-xs font-bold font-mono text-indigo-300">#{item.order}</span>
                          <Badge variant="slate">{item.section_label}</Badge>
                          <Badge variant="indigo">{item.visual_type_label}</Badge>
                          <span className="text-[10px] text-slate-500 flex items-center gap-1">
                            <Clock className="w-3 h-3" />
                            {item.duration_seconds}s
                          </span>
                        </div>
                        <p className="text-xs text-slate-200">{item.narration_text}</p>
                        <p className="text-[11px] text-slate-400">{item.visual_prompt}</p>
                        {item.notes && <p className="text-[11px] text-slate-500">Notes: {item.notes}</p>}
                      </div>
                      <div className="flex items-center gap-1 shrink-0">
                        <Button
                          variant="ghost"
                          size="sm"
                          icon={<ArrowUp className="w-3.5 h-3.5" />}
                          disabled={reordering || index === 0}
                          onClick={() => handleMove(index, -1)}
                        >
                          <span className="sr-only">Move item {item.order} up</span>
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          icon={<ArrowDown className="w-3.5 h-3.5" />}
                          disabled={reordering || index === items.length - 1}
                          onClick={() => handleMove(index, 1)}
                        >
                          <span className="sr-only">Move item {item.order} down</span>
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          icon={<Pencil className="w-3.5 h-3.5" />}
                          disabled={editingItem !== null}
                          onClick={() => openEditItem(item)}
                        >
                          <span className="sr-only">Edit item {item.order}</span>
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          icon={<Trash2 className="w-3.5 h-3.5 text-rose-400" />}
                          onClick={() => handleDeleteItem(item)}
                        >
                          <span className="sr-only">Delete item {item.order}</span>
                        </Button>
                      </div>
                    </div>

                    {editingItem === item.id && renderItemForm('edit', item)}
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          {/* CONFIRM DESTRUCTIVE TRANSITION */}
          {confirmTransition && (
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
              <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
                <div className="flex items-center gap-3 mb-4">
                  <div className="p-2 rounded-lg bg-rose-500/10 text-rose-400">
                    <ShieldCheck className="w-6 h-6" />
                  </div>
                  <div>
                    <h3 className="text-base font-bold text-white">{confirmTransition.action}</h3>
                    <p className="text-xs text-slate-400">Visual plan status change</p>
                  </div>
                </div>
                <p className="text-xs text-slate-300 leading-relaxed mb-6">
                  This will move the visual plan from{' '}
                  <span className="font-bold text-white">{plan.status_label}</span> to{' '}
                  <span className="font-bold text-white">{confirmTransition.label}</span>. Items are never
                  deleted; an archived plan can be restored to draft.
                </p>
                <div className="flex items-center justify-end gap-3">
                  <Button variant="outline" size="sm" onClick={() => setConfirmTransition(null)}>
                    Cancel
                  </Button>
                  <Button
                    variant="danger"
                    size="sm"
                    isLoading={transitioning === confirmTransition.status}
                    onClick={() => handleTransition(confirmTransition.status)}
                  >
                    Confirm
                  </Button>
                </div>
              </Card>
            </div>
          )}
        </>
      )}
    </div>
  );
};
