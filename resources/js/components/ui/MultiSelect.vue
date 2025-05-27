<template>
    <div class="relative w-full">
        <!-- Badges sélectionnés en dehors du trigger -->
        <div v-if="selectedValues.length > 0" class="flex flex-wrap items-center gap-1 mb-2">
            <Badge
                v-for="value in selectedValues.slice(0, maxCount)"
                :key="value"
                :class="cn(
                    'transition-all duration-200 ease-in-out',
                    isAnimating ? 'animate-bounce' : '',
                    multiSelectVariants({ variant })
                )"
                :style="{ animationDuration: `${animation}s` }"
            >
                <component
                    v-if="getOptionIcon(value)"
                    :is="getOptionIcon(value)"
                    class="h-4 w-4 mr-2 text-current"
                />
                {{ getOptionLabel(value) }}
                <button
                    type="button"
                    class="ml-2 h-5 w-5 cursor-pointer text-red-500 hover:text-red-700 bg-red-100 hover:bg-red-200 rounded-full p-1 transition-all duration-200 hover:scale-110 flex-shrink-0 flex items-center justify-center border-0 outline-none"
                    @click.stop.prevent="toggleOption(value)"
                    title="Supprimer"
                >
                    <XCircle class="h-3 w-3" />
                </button>
            </Badge>
            <Badge
                v-if="selectedValues.length > maxCount"
                :class="cn(
                    'bg-transparent text-foreground border-foreground/1 hover:bg-transparent transition-all duration-200 ease-in-out',
                    isAnimating ? 'animate-bounce' : '',
                    multiSelectVariants({ variant })
                )"
                :style="{ animationDuration: `${animation}s` }"
            >
                + {{ selectedValues.length - maxCount }} more
                <button
                    type="button"
                    class="ml-2 h-5 w-5 cursor-pointer text-red-500 hover:text-red-700 bg-red-100 hover:bg-red-200 rounded-full p-1 transition-all duration-200 hover:scale-110 flex-shrink-0 flex items-center justify-center border-0 outline-none"
                    @click.stop.prevent="clearExtraOptions"
                    title="Supprimer"
                >
                    <XCircle class="h-3 w-3" />
                </button>
            </Badge>
        </div>

        <Popover v-model:open="isPopoverOpen" :modal="modalPopover">
            <PopoverTrigger as-child>
                <div
                    ref="triggerRef"
                    v-bind="$attrs"
                    :class="cn(
                        'flex w-full p-1 rounded-md border min-h-10 h-auto items-center justify-between bg-background hover:bg-accent cursor-pointer transition-colors',
                        disabled ? 'opacity-50 cursor-not-allowed' : '',
                        className
                    )"
                >
                    <div class="flex items-center justify-between w-full">
                        <span class="text-sm text-muted-foreground mx-3">
                            {{ selectedValues.length > 0 
                                ? (props.maxSelections === 1 
                                    ? getOptionLabel(selectedValues[0]) 
                                    : `${selectedValues.length} selected`)
                                : placeholder }}
                        </span>
                        <div class="flex items-center">
                            <button
                                v-if="selectedValues.length > 0"
                                type="button"
                                class="h-5 w-5 cursor-pointer text-red-500 hover:text-red-700 bg-red-100 hover:bg-red-200 rounded-full p-1 transition-all duration-200 hover:scale-110 flex-shrink-0 flex items-center justify-center border-0 outline-none mx-2"
                                @click.stop.prevent="handleClear"
                                title="Tout supprimer"
                            >
                                <XIcon class="h-3 w-3" />
                            </button>
                            <ChevronDown class="h-4 mx-2 cursor-pointer text-muted-foreground hover:text-foreground transition-colors duration-150" />
                        </div>
                    </div>
                </div>
            </PopoverTrigger>
            <PopoverContent
                class="w-auto p-0"
                align="start"
                @escape-key-down="isPopoverOpen = false"
            >
                <Command>
                    <CommandInput
                        placeholder="Search..."
                        @keydown="handleInputKeyDown"
                    />
                    <CommandList>
                        <CommandEmpty>No results found.</CommandEmpty>
                        <CommandGroup>
                            <CommandItem
                                v-if="props.maxSelections !== 1"
                                key="all"
                                value="select-all"
                                @select="toggleAll"
                                class="cursor-pointer hover:bg-accent transition-colors duration-150"
                            >
                                <div
                                    :class="cn(
                                        'mr-2 flex h-4 w-4 items-center justify-center rounded-sm border-2 transition-all duration-200',
                                        selectedValues.length === options.length
                                            ? 'bg-green-600 border-green-600 text-white shadow-sm'
                                            : 'border-gray-300 hover:border-green-500'
                                    )"
                                >
                                    <CheckIcon 
                                        :class="cn(
                                            'h-3 w-3 transition-all duration-200',
                                            selectedValues.length === options.length ? 'opacity-100 scale-100 text-white' : 'opacity-0 scale-75 text-transparent'
                                        )" 
                                    />
                                </div>
                                <span class="font-medium">(Select All)</span>
                            </CommandItem>
                            <CommandItem
                                v-for="option in options"
                                :key="option.value"
                                :value="option.value"
                                @select="() => toggleOption(option.value)"
                                class="cursor-pointer hover:bg-accent transition-colors duration-150"
                            >
                                <div
                                    :class="cn(
                                        'mr-2 flex h-4 w-4 items-center justify-center rounded-sm border-2 transition-all duration-200',
                                        selectedValues.includes(option.value)
                                            ? 'bg-green-600 border-green-600 text-white shadow-sm'
                                            : 'border-gray-300 hover:border-green-500'
                                    )"
                                >
                                    <CheckIcon 
                                        :class="cn(
                                            'h-3 w-3 transition-all duration-200',
                                            selectedValues.includes(option.value) ? 'opacity-100 scale-100 text-white' : 'opacity-0 scale-75 text-transparent'
                                        )" 
                                    />
                                </div>
                                <component
                                    v-if="option.icon"
                                    :is="option.icon"
                                    class="mr-2 h-4 w-4 text-current"
                                />
                                <span>{{ option.label }}</span>
                            </CommandItem>
                        </CommandGroup>
                        <CommandSeparator />
                        <CommandGroup>
                            <div class="flex items-center justify-between">
                                <template v-if="selectedValues.length > 0">
                                    <CommandItem
                                        value="clear"
                                        @select="handleClear"
                                        class="flex-1 justify-center cursor-pointer hover:bg-destructive/10 hover:text-destructive transition-colors duration-150"
                                    >
                                        <span class="font-medium">Clear</span>
                                    </CommandItem>
                                    <Separator
                                        orientation="vertical"
                                        class="flex min-h-6 h-full"
                                    />
                                </template>
                                <CommandItem
                                    value="close"
                                    @select="() => isPopoverOpen = false"
                                    class="flex-1 justify-center cursor-pointer max-w-full hover:bg-accent transition-colors duration-150"
                                >
                                    <span class="font-medium">Close</span>
                                </CommandItem>
                            </div>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
            <WandSparkles
                v-if="animation > 0 && selectedValues.length > 0"
                :class="cn(
                    'cursor-pointer my-2 text-foreground bg-background w-3 h-3',
                    isAnimating ? '' : 'text-muted-foreground'
                )"
                @click="isAnimating = !isAnimating"
            />
        </Popover>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, nextTick, type Component } from 'vue';
import { cva, type VariantProps } from 'class-variance-authority';
import {
    CheckIcon,
    XCircle,
    ChevronDown,
    XIcon,
    WandSparkles,
} from 'lucide-vue-next';

import { cn } from '@/lib/utils';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';

/**
 * Variants for the multi-select component to handle different styles.
 * Uses class-variance-authority (cva) to define different styles based on "variant" prop.
 */
const multiSelectVariants = cva(
    'm-1 transition ease-in-out delay-150 hover:-translate-y-1 hover:scale-110 duration-300',
    {
        variants: {
            variant: {
                default:
                    'border-foreground/10 text-foreground bg-card hover:bg-card/80',
                secondary:
                    'border-foreground/10 bg-secondary text-secondary-foreground hover:bg-secondary/80',
                destructive:
                    'border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80',
                inverted: 'inverted',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    }
);

/**
 * Option interface for MultiSelect component
 */
interface MultiSelectOption {
    /** The text to display for the option. */
    label: string;
    /** The unique value associated with the option. */
    value: string;
    /** Optional icon component to display alongside the option. */
    icon?: Component;
}

interface MultiSelectProps extends /* @vue-ignore */ VariantProps<typeof multiSelectVariants> {
    /**
     * An array of option objects to be displayed in the multi-select component.
     * Each option object has a label, value, and an optional icon.
     */
    options: MultiSelectOption[];

    /**
     * The default selected values when the component mounts.
     */
    modelValue?: string[];

    /**
     * Placeholder text to be displayed when no values are selected.
     * Optional, defaults to "Select options".
     */
    placeholder?: string;

    /**
     * Animation duration in seconds for the visual effects (e.g., bouncing badges).
     * Optional, defaults to 0 (no animation).
     */
    animation?: number;

    /**
     * Maximum number of items to display. Extra selected items will be summarized.
     * Optional, defaults to 3.
     */
    maxCount?: number;

    /**
     * Maximum number of items that can be selected.
     * Optional, defaults to unlimited. Set to 1 for single select mode.
     */
    maxSelections?: number;

    /**
     * The modality of the popover. When set to true, interaction with outside elements
     * will be disabled and only popover content will be visible to screen readers.
     * Optional, defaults to false.
     */
    modalPopover?: boolean;

    /**
     * Additional class names to apply custom styles to the multi-select component.
     * Optional, can be used to add custom styles.
     */
    className?: string;

    /**
     * Whether the multi-select is disabled.
     * Optional, defaults to false.
     */
    disabled?: boolean;
}

const props = withDefaults(defineProps<MultiSelectProps>(), {
    modelValue: () => [],
    placeholder: 'Select options',
    animation: 0,
    maxCount: 3,
    maxSelections: Infinity,
    modalPopover: false,
    className: '',
    variant: 'default',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const selectedValues = ref<string[]>([...props.modelValue]);
const isPopoverOpen = ref(false);
const isAnimating = ref(false);
const triggerRef = ref();

// Watch for external changes to modelValue
watch(
    () => props.modelValue,
    (newValue, oldValue) => {
        // Éviter les mises à jour inutiles
        if (JSON.stringify(newValue) !== JSON.stringify(selectedValues.value)) {
            selectedValues.value = [...newValue];
        }
    },
    { deep: true }
);

// Emit changes when selectedValues changes
watch(
    selectedValues,
    (newValue, oldValue) => {
        // Éviter les émissions inutiles
        if (JSON.stringify(newValue) !== JSON.stringify(oldValue)) {
            emit('update:modelValue', newValue);
        }
    },
    { deep: true }
);

const handleInputKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Enter') {
        isPopoverOpen.value = true;
    } else if (event.key === 'Backspace' && !(event.target as HTMLInputElement).value) {
        const newSelectedValues = [...selectedValues.value];
        newSelectedValues.pop();
        selectedValues.value = newSelectedValues;
    }
};

const toggleOption = async (option: string) => {
    const isCurrentlySelected = selectedValues.value.includes(option);
    
    if (isCurrentlySelected) {
        // Toujours permettre de désélectionner
        selectedValues.value = selectedValues.value.filter((value) => value !== option);
    } else {
        // Vérifier la limite avant d'ajouter
        if (selectedValues.value.length >= props.maxSelections) {
            // Si on est en mode single select (maxSelections = 1), remplacer la sélection
            if (props.maxSelections === 1) {
                selectedValues.value = [option];
                // Fermer automatiquement le popover en mode single select
                await nextTick();
                isPopoverOpen.value = false;
            }
            // Sinon, ne rien faire (limite atteinte)
            return;
        } else {
            // Ajouter normalement
            selectedValues.value = [...selectedValues.value, option];
            
            // Fermer automatiquement le popover en mode single select
            if (props.maxSelections === 1) {
                await nextTick();
                isPopoverOpen.value = false;
            }
        }
    }
    
    await nextTick(); // Assure une mise à jour fluide
};

const handleClear = async () => {
    selectedValues.value = [];
    await nextTick(); // Assure une mise à jour fluide
};

const clearExtraOptions = async () => {
    const newSelectedValues = selectedValues.value.slice(0, props.maxCount);
    selectedValues.value = newSelectedValues;
    await nextTick(); // Assure une mise à jour fluide
};

const toggleAll = async () => {
    if (selectedValues.value.length === props.options.length) {
        selectedValues.value = [];
    } else {
        // Respecter la limite maxSelections
        const maxToSelect = Math.min(props.options.length, props.maxSelections);
        const allValues = props.options.map((option) => option.value).slice(0, maxToSelect);
        selectedValues.value = allValues;
    }
    await nextTick(); // Assure une mise à jour fluide
};

const getOptionLabel = (value: string) => {
    return props.options.find((o) => o.value === value)?.label || '';
};

const getOptionIcon = (value: string) => {
    return props.options.find((o) => o.value === value)?.icon;
};
</script> 