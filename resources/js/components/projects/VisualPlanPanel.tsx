import React, { useEffect, useState } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { QualityMetric } from '../ui/QualityMetric';
import { getApiErrorMessage } from '../../services/researchService';
import { scriptService } from '../../services/scriptService';
import { visualPlanService } from '../../services/visualPlanService';
import { assetRequirementService } from '../../services/assetRequirementService';
import {
  AssetRequirement,
  AssetRequirementAspectRatio,
  AssetRequirementFormData,
  AssetRequirementStatus,
  AssetRequirementTransition,
  AssetRequirementType,
  Script,
  ScriptQualityResult,
  VisualPlan,
  VisualPlanItem,
  VisualPlanItemFormData,
  VisualPlanItemType,
  VisualPlanQualityIssue,
  VisualPlanQualityResult,
  VisualPlanSection,
  VisualPlanStatus,
  VisualPlanTransition,
} from '../../types';
import {
  AlertCircle,
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  CheckCircle2,
  Clapperboard,
  Clock,
  Download,
  Gauge,
  Layers,
  Lightbulb,
  Package,
  Pencil,
  Plus,
  Save,
  ShieldCheck,
  Sparkles,
  Trash2,
  Wand2,
  X,
  XCircle,
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

interface RequirementFormState {
  requirement_type: AssetRequirementType;
  search_query: string;
  description: string;
  target_duration_seconds: string;
  aspect_ratio: AssetRequirementAspectRatio | '';
  notes: string;
}

const emptyRequirementForm = (): RequirementFormState => ({
  requirement_type: 'video',
  search_query: '',
  description: '',
  target_duration_seconds: '',
  aspect_ratio: '9:16',
  notes: '',
});

const toRequirementForm = (requirement: AssetRequirement): RequirementFormState => ({
  requirement_type: requirement.requirement_type,
  search_query: requirement.search_query ?? '',
  description: requirement.description,
  target_duration_seconds:
    requirement.target_duration_seconds === null || requirement.target_duration_seconds === undefined
      ? ''
      : String(requirement.target_duration_seconds),
  aspect_ratio: requirement.aspect_ratio ?? '',
  notes: requirement.notes ?? '',
});

const requirementTypeOptions: { value: AssetRequirementType; label: string }[] = [
  { value: 'video', label: 'Video' },
  { value: 'image', label: 'Image' },
  { value: 'audio', label: 'Audio' },
  { value: 'graphic', label: 'Graphic' },
  { value: 'screen_recording', label: 'Screen Recording' },
  { value: 'other', label: 'Other' },
];

const aspectRatioOptions: { value: AssetRequirementAspectRatio; label: string }[] = [
  { value: '9:16', label: '9:16 (vertical)' },
  { value: '16:9', label: '16:9 (landscape)' },
  { value: '1:1', label: '1:1 (square)' },
  { value: '4:5', label: '4:5 (portrait)' },
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

function getRequirementStatusVariant(status: AssetRequirementStatus) {
  switch (status) {
    case 'searching':
      return 'violet';
    case 'fulfilled':
      return 'emerald';
    case 'skipped':
      return 'slate';
    default:
      return 'amber';
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

  const [editingRequirement, setEditingRequirement] = useState<number | 'new' | null>(null);
  const [requirementItemId, setRequirementItemId] = useState<number | null>(null);
  const [requirementForm, setRequirementForm] = useState<RequirementFormState>(emptyRequirementForm());
  const [requirementSaving, setRequirementSaving] = useState<boolean>(false);
  const [requirementStatusBusy, setRequirementStatusBusy] = useState<number | null>(null);
  const [generating, setGenerating] = useState<boolean>(false);
  const [confirmGenerate, setConfirmGenerate] = useState<boolean>(false);

  const [readiness, setReadiness] = useState<VisualPlanQualityResult | null>(null);
  const [readinessLoading, setReadinessLoading] = useState<boolean>(false);

  const version = script?.current_version?.version ?? null;
  const scriptReviewable = script?.status === 'review' || script?.status === 'approved';
  const scriptReady = script !== null && scriptReviewable && quality?.ready === true;

  const refreshItems = async (versionNumber: number) => {
    const data = await visualPlanService.listItems(projectId, versionNumber);
    setItems(data);
  };

  /**
   * Re-read the readiness gate. It is a separate read-only endpoint, so a
   * failure here must never look like a failed mutation to the user.
   */
  const refreshReadiness = async (versionNumber: number) => {
    setReadinessLoading(true);
    try {
      setReadiness(await visualPlanService.getQuality(projectId, versionNumber));
    } catch {
      setReadiness(null);
    } finally {
      setReadinessLoading(false);
    }
  };

  /**
   * Every mutation funnels through here so the gate never drifts from the
   * items and requirements it describes.
   */
  const refreshPlanData = async (versionNumber: number) => {
    await Promise.all([refreshItems(versionNumber), refreshReadiness(versionNumber)]);
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
          setReadiness(null);
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

        if (planData) {
          const [itemData, readinessData] = await Promise.all([
            visualPlanService.listItems(projectId, currentVersion),
            visualPlanService.getQuality(projectId, currentVersion).catch(() => null),
          ]);
          if (!active) return;
          setItems(itemData);
          setReadiness(readinessData);
        } else {
          setItems([]);
          setReadiness(null);
        }
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
      await refreshPlanData(version);
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
      await refreshReadiness(version);
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
      await refreshPlanData(version);
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
      await refreshPlanData(version);
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
      await refreshPlanData(version);
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to reorder the visual plan items.'));
    } finally {
      setReordering(false);
    }
  };

  const openNewRequirement = (item: VisualPlanItem) => {
    setRequirementForm({
      ...emptyRequirementForm(),
      description: item.visual_prompt,
      target_duration_seconds: String(item.duration_seconds),
    });
    setRequirementItemId(item.id);
    setEditingRequirement('new');
  };

  const openEditRequirement = (item: VisualPlanItem, requirement: AssetRequirement) => {
    setRequirementForm(toRequirementForm(requirement));
    setRequirementItemId(item.id);
    setEditingRequirement(requirement.id);
  };

  const closeRequirementForm = () => {
    setEditingRequirement(null);
    setRequirementItemId(null);
  };

  const handleSaveRequirement = async () => {
    if (version === null || requirementItemId === null) return;

    const payload: AssetRequirementFormData = {
      requirement_type: requirementForm.requirement_type,
      search_query: requirementForm.search_query.trim() || null,
      description: requirementForm.description.trim(),
      target_duration_seconds: requirementForm.target_duration_seconds
        ? Number(requirementForm.target_duration_seconds)
        : null,
      aspect_ratio: requirementForm.aspect_ratio === '' ? null : requirementForm.aspect_ratio,
      notes: requirementForm.notes.trim() || null,
    };

    setRequirementSaving(true);
    setMessage(null);
    setPanelError(null);
    try {
      if (editingRequirement === 'new') {
        await assetRequirementService.create(projectId, version, requirementItemId, payload);
        setMessage('Asset requirement added successfully.');
      } else if (editingRequirement !== null) {
        await assetRequirementService.update(projectId, version, requirementItemId, editingRequirement, payload);
        setMessage('Asset requirement updated successfully.');
      }

      closeRequirementForm();
      await refreshPlanData(version);
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to save the asset requirement.'));
    } finally {
      setRequirementSaving(false);
    }
  };

  const handleDeleteRequirement = async (itemId: number, requirementId: number) => {
    if (version === null) return;
    setMessage(null);
    setPanelError(null);
    try {
      await assetRequirementService.remove(projectId, version, itemId, requirementId);
      await refreshPlanData(version);
      setMessage('Asset requirement removed.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to remove the asset requirement.'));
    }
  };

  const handleRequirementTransition = async (
    itemId: number,
    requirementId: number,
    target: AssetRequirementStatus,
  ) => {
    if (version === null) return;
    setRequirementStatusBusy(requirementId);
    setMessage(null);
    setPanelError(null);
    try {
      await assetRequirementService.transitionStatus(projectId, version, itemId, requirementId, target);
      await refreshPlanData(version);
      setMessage('Asset requirement status updated.');
    } catch (err) {
      setPanelError(getApiErrorMessage(err, 'Unable to update the asset requirement status.'));
    } finally {
      setRequirementStatusBusy(null);
    }
  };

  const handleGenerate = async () => {
    if (version === null) return;
    setGenerating(true);
    setMessage(null);
    setPanelError(null);
    try {
      const summary = await assetRequirementService.generate(projectId, version);
      setConfirmGenerate(false);
      await refreshPlanData(version);
      setMessage(
        `Generated ${summary.created} requirement${summary.created === 1 ? '' : 's'}. ` +
          `${summary.existing} already existed and ${summary.skipped} were skipped.`,
      );
    } catch (err) {
      setConfirmGenerate(false);
      setPanelError(getApiErrorMessage(err, 'Unable to generate asset requirements.'));
    } finally {
      setGenerating(false);
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

  const renderRequirementForm = (mode: 'create' | 'edit') => (
    <Card variant="default" className="border-amber-500/30 mt-2">
      <CardHeader>
        <CardTitle className="text-xs font-bold text-white flex items-center gap-2">
          {mode === 'create' ? (
            <Plus className="w-3.5 h-3.5 text-amber-400" />
          ) : (
            <Pencil className="w-3.5 h-3.5 text-amber-400" />
          )}
          {mode === 'create' ? 'New Asset Requirement' : 'Edit Asset Requirement'}
        </CardTitle>
        <CardDescription>
          Describe the media you will need for this item. Nothing is downloaded or searched here yet.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label className={labelClasses} htmlFor="asset-requirement-type">
              Asset Type
            </label>
            <select
              id="asset-requirement-type"
              className={inputClasses}
              value={requirementForm.requirement_type}
              onChange={(e) =>
                setRequirementForm({
                  ...requirementForm,
                  requirement_type: e.target.value as AssetRequirementType,
                })
              }
            >
              {requirementTypeOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className={labelClasses} htmlFor="asset-requirement-duration">
              Target Duration (seconds)
            </label>
            <input
              id="asset-requirement-duration"
              type="number"
              min={1}
              max={600}
              className={inputClasses}
              value={requirementForm.target_duration_seconds}
              onChange={(e) =>
                setRequirementForm({ ...requirementForm, target_duration_seconds: e.target.value })
              }
            />
          </div>
          <div>
            <label className={labelClasses} htmlFor="asset-requirement-aspect">
              Aspect Ratio
            </label>
            <select
              id="asset-requirement-aspect"
              className={inputClasses}
              value={requirementForm.aspect_ratio}
              onChange={(e) =>
                setRequirementForm({
                  ...requirementForm,
                  aspect_ratio: e.target.value as AssetRequirementAspectRatio | '',
                })
              }
            >
              <option value="">Not set</option>
              {aspectRatioOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div>
          <label className={labelClasses} htmlFor="asset-requirement-description">
            Description
          </label>
          <textarea
            id="asset-requirement-description"
            rows={2}
            className={inputClasses}
            value={requirementForm.description}
            onChange={(e) => setRequirementForm({ ...requirementForm, description: e.target.value })}
            placeholder="What this asset must show"
          />
        </div>

        <div>
          <label className={labelClasses} htmlFor="asset-requirement-query">
            Search Query
          </label>
          <input
            id="asset-requirement-query"
            className={inputClasses}
            value={requirementForm.search_query}
            onChange={(e) => setRequirementForm({ ...requirementForm, search_query: e.target.value })}
            placeholder="Optional keywords to search for later"
          />
        </div>

        <div>
          <label className={labelClasses} htmlFor="asset-requirement-notes">
            Notes
          </label>
          <input
            id="asset-requirement-notes"
            className={inputClasses}
            value={requirementForm.notes}
            onChange={(e) => setRequirementForm({ ...requirementForm, notes: e.target.value })}
            placeholder="Optional sourcing notes"
          />
        </div>

        <div className="flex items-center justify-end gap-3">
          <Button
            variant="outline"
            size="sm"
            icon={<X className="w-3.5 h-3.5" />}
            onClick={closeRequirementForm}
          >
            Cancel
          </Button>
          <Button
            size="sm"
            icon={<Save className="w-3.5 h-3.5" />}
            isLoading={requirementSaving}
            onClick={handleSaveRequirement}
          >
            Save Requirement
          </Button>
        </div>
      </CardContent>
    </Card>
  );

  const renderRequirementRow = (item: VisualPlanItem, requirement: AssetRequirement) => (
    <div className="rounded-lg border border-amber-900/40 bg-amber-950/10 px-3 py-2 space-y-1.5">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 space-y-1">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="amber">{requirement.requirement_type_label}</Badge>
            <Badge variant={getRequirementStatusVariant(requirement.status)}>
              {requirement.status_label}
            </Badge>
            {requirement.target_duration_seconds !== null &&
              requirement.target_duration_seconds !== undefined && (
                <span className="text-[10px] text-slate-500 flex items-center gap-1">
                  <Clock className="w-3 h-3" />
                  {requirement.target_duration_seconds}s
                </span>
              )}
            {requirement.aspect_ratio && (
              <span className="text-[10px] text-slate-500">{requirement.aspect_ratio}</span>
            )}
          </div>
          <p className="text-[11px] text-slate-300">{requirement.description}</p>
          {requirement.search_query && (
            <p className="text-[10px] text-slate-500 font-mono">Query: {requirement.search_query}</p>
          )}
          {requirement.notes && <p className="text-[10px] text-slate-500">Notes: {requirement.notes}</p>}
        </div>
        <div className="flex items-center gap-1 shrink-0">
          <Button
            variant="ghost"
            size="sm"
            icon={<Pencil className="w-3.5 h-3.5" />}
            disabled={editingRequirement !== null}
            onClick={() => openEditRequirement(item, requirement)}
          >
            <span className="sr-only">Edit asset requirement {requirement.id}</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            icon={<Trash2 className="w-3.5 h-3.5 text-rose-400" />}
            onClick={() => handleDeleteRequirement(item.id, requirement.id)}
          >
            <span className="sr-only">Delete asset requirement {requirement.id}</span>
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {requirement.allowed_transitions.length === 0 ? (
          <p className="text-[10px] text-slate-500">
            This requirement is {requirement.status_label.toLowerCase()} and cannot change status.
          </p>
        ) : (
          requirement.allowed_transitions.map((transition: AssetRequirementTransition) => (
            <Button
              key={transition.status}
              variant={transition.destructive ? 'outline' : 'secondary'}
              size="sm"
              icon={
                transition.status === 'fulfilled' ? (
                  <CheckCircle2 className="w-3.5 h-3.5" />
                ) : (
                  <Package className="w-3.5 h-3.5" />
                )
              }
              isLoading={requirementStatusBusy === requirement.id}
              disabled={requirementStatusBusy !== null}
              onClick={() => handleRequirementTransition(item.id, requirement.id, transition.status)}
            >
              {transition.action}
            </Button>
          ))
        )}
      </div>
    </div>
  );

  const renderItemRequirements = (item: VisualPlanItem) => (
    <div className="space-y-2 border-t border-slate-800 pt-2">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Package className="w-3.5 h-3.5 text-amber-400" />
          <span className="text-[11px] font-semibold text-slate-400">Asset Requirements</span>
          <Badge variant="slate">{item.asset_requirements?.length ?? 0}</Badge>
        </div>
        <Button
          variant="ghost"
          size="sm"
          icon={<Plus className="w-3.5 h-3.5" />}
          disabled={editingRequirement !== null}
          onClick={() => openNewRequirement(item)}
        >
          Add
        </Button>
      </div>

      {editingRequirement === 'new' && requirementItemId === item.id && renderRequirementForm('create')}

      {item.asset_requirements && item.asset_requirements.length > 0 ? (
        <div className="space-y-2">
          {item.asset_requirements.map((requirement) => (
            <React.Fragment key={requirement.id}>
              {renderRequirementRow(item, requirement)}
              {editingRequirement === requirement.id && requirementItemId === item.id && (
                <div>{renderRequirementForm('edit')}</div>
              )}
            </React.Fragment>
          ))}
        </div>
      ) : (
        <p className="text-[10px] text-slate-500">
          No assets required for this item yet. Generate requirements for the whole plan, or add one here.
        </p>
      )}
    </div>
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
                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    icon={<Plus className="w-3.5 h-3.5" />}
                    onClick={openNewItem}
                    disabled={editingItem !== null}
                  >
                    Add Item
                  </Button>
                  <Button
                    variant="secondary"
                    size="sm"
                    icon={<Wand2 className="w-3.5 h-3.5" />}
                    disabled={items.length === 0}
                    onClick={() => setConfirmGenerate(true)}
                  >
                    Generate Requirements
                  </Button>
                </div>
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

                    {renderItemRequirements(item)}
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          {/* VISUAL PRODUCTION READINESS */}
          <Card variant="default">
            <CardHeader>
              <div className="flex items-center justify-between gap-3 flex-wrap">
                <div>
                  <CardTitle className="text-base font-bold text-white flex items-center gap-2">
                    <Gauge className="w-4 h-4 text-indigo-400" />
                    Visual Production Readiness
                    {readiness && (
                      <Badge variant={readiness.ready ? 'emerald' : 'rose'}>
                        {readiness.ready ? 'READY' : 'NOT READY'}
                      </Badge>
                    )}
                    {readiness && (
                      <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-300 bg-slate-950 border border-slate-800 rounded-lg px-2 py-0.5">
                        Score {readiness.score}/100
                      </span>
                    )}
                  </CardTitle>
                  <CardDescription>
                    Deterministic check that the plan is complete enough to source assets. It never claims an
                    asset exists, and nothing is searched or downloaded here.
                  </CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              {readinessLoading && !readiness ? (
                <div className="h-20 bg-slate-900 rounded-xl animate-pulse" />
              ) : !readiness ? (
                <p className="text-xs text-slate-500">
                  Production readiness is unavailable. Items and requirements are unaffected.
                </p>
              ) : (
                <>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <QualityMetric
                      label="Items covered"
                      value={`${readiness.summary.items_with_requirements}/${readiness.summary.total_items}`}
                      tone={
                        readiness.summary.items_without_requirements > 0 ? 'rose' : 'emerald'
                      }
                    />
                    <QualityMetric
                      label="Requirements valid"
                      value={`${readiness.summary.valid_requirements}/${readiness.summary.total_requirements}`}
                      tone={readiness.summary.invalid_requirements > 0 ? 'rose' : 'emerald'}
                    />
                    <QualityMetric
                      label="Blockers"
                      value={String(readiness.summary.blocker_count)}
                      tone={readiness.summary.blocker_count > 0 ? 'rose' : 'emerald'}
                    />
                    <QualityMetric
                      label="Warnings"
                      value={String(readiness.summary.warning_count)}
                      tone={readiness.summary.warning_count > 0 ? 'amber' : 'emerald'}
                    />
                  </div>

                  {readiness.blockers.length > 0 && (
                    <div>
                      <span className="block text-[11px] font-semibold text-rose-300 mb-2">
                        Blockers · must be resolved before sourcing assets
                      </span>
                      <div className="space-y-1.5">
                        {readiness.blockers.map((issue, index) => (
                          <ReadinessIssueRow key={`${issue.code}-${index}`} issue={issue} />
                        ))}
                      </div>
                    </div>
                  )}

                  {readiness.warnings.length > 0 && (
                    <div>
                      <span className="block text-[11px] font-semibold text-amber-300 mb-2">
                        Warnings · worth fixing, but they do not block
                      </span>
                      <div className="space-y-1.5">
                        {readiness.warnings.map((issue, index) => (
                          <ReadinessIssueRow key={`${issue.code}-${index}`} issue={issue} />
                        ))}
                      </div>
                    </div>
                  )}

                  {readiness.info.length > 0 && (
                    <div>
                      <span className="block text-[11px] font-semibold text-slate-400 mb-2">Notes</span>
                      <div className="space-y-1.5">
                        {readiness.info.map((issue, index) => (
                          <ReadinessIssueRow key={`${issue.code}-${index}`} issue={issue} />
                        ))}
                      </div>
                    </div>
                  )}

                  <p className="text-[11px] text-slate-500">
                    Planning readiness only. Assets are not sourced, tracked, or verified by this gate.
                  </p>
                </>
              )}
            </CardContent>
          </Card>

          {/* CONFIRM GENERATION */}          {confirmGenerate && (
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
              <Card variant="default" className="max-w-md w-full p-6 border-slate-800 shadow-2xl">
                <div className="flex items-center gap-3 mb-4">
                  <div className="p-2 rounded-lg bg-amber-500/10 text-amber-400">
                    <Wand2 className="w-6 h-6" />
                  </div>
                  <div>
                    <h3 className="text-base font-bold text-white">Generate Asset Requirements</h3>
                    <p className="text-xs text-slate-400">Deterministic, no AI involved</p>
                  </div>
                </div>
                <p className="text-xs text-slate-300 leading-relaxed mb-4">
                  Every plan item without an asset requirement will get one pending requirement, with the
                  type derived from the item visual type and the duration copied from the item.
                </p>
                <div className="flex items-start gap-2 p-3 rounded-lg bg-slate-900/60 border border-slate-800 mb-6">
                  <Download className="w-4 h-4 shrink-0 mt-0.5 text-slate-400" />
                  <p className="text-[11px] text-slate-400">
                    Items that already have a requirement are left untouched, so your edits are safe. No media
                    is downloaded or searched in this step.
                  </p>
                </div>
                <div className="flex items-center justify-end gap-3">
                  <Button variant="outline" size="sm" onClick={() => setConfirmGenerate(false)}>
                    Cancel
                  </Button>
                  <Button size="sm" icon={<Wand2 className="w-3.5 h-3.5" />} isLoading={generating} onClick={handleGenerate}>
                    Generate
                  </Button>
                </div>
              </Card>
            </div>
          )}

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

/**
 * One readiness issue. Blockers and warnings only ever contain failures, so
 * severity alone decides the icon and colour.
 */
function ReadinessIssueRow({ issue }: { issue: VisualPlanQualityIssue }) {
  const tone =
    issue.severity === 'blocker'
      ? { icon: <XCircle className="w-3.5 h-3.5 shrink-0 text-rose-400" />, code: 'text-rose-300' }
      : issue.severity === 'warning'
        ? { icon: <AlertTriangle className="w-3.5 h-3.5 shrink-0 text-amber-400" />, code: 'text-amber-300' }
        : { icon: <CheckCircle2 className="w-3.5 h-3.5 shrink-0 text-indigo-400" />, code: 'text-slate-400' };

  return (
    <div className="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2">
      {tone.icon}
      <span className={`text-[11px] font-mono font-semibold shrink-0 ${tone.code}`}>{issue.code}</span>
      <span className="text-xs text-slate-300 min-w-0">{issue.message}</span>
    </div>
  );
}
