<template>
    <Popover v-model:open="isPopoverOpen" :modal="modalPopover">
        <PopoverTrigger as-child>
            <Button
                ref="triggerRef"
                v-bind="$attrs"
                @click="handleTogglePopover"
                :class="cn(
                    'flex w-full p-1 rounded-md border min-h-10 h-auto items-center justify-between bg-inherit hover:bg-inherit [&_svg]:pointer-events-auto',
                    className
                )"
            >
                <div v-if="selectedValues.length > 0" class="flex justify-between items-center w-full">
                    <div class="flex flex-wrap items-center">
                        <Badge
                            v-for="value in selectedValues.slice(0, maxCount)"
                            :key="value"
                            :class="cn(
                                isAnimating ? 'animate-bounce' : '',
                                multiSelectVariants({ variant })
                            )"
                            :style="{ animationDuration: `${animation}s` }"
                        >
                            <component
                                v-if="getOptionIcon(value)"
                                :is="getOptionIcon(value)"
                                class="h-4 w-4 mr-2"
                            />
                            {{ getOptionLabel(value) }}
                            <XCircle
                                class="ml-2 h-4 w-4 cursor-pointer"
                                @click.stop="toggleOption(value)"
                            />
                        </Badge>
                        <Badge
                            v-if="selectedValues.length > maxCount"
                            :class="cn(
                                'bg-transparent text-foreground border-foreground/1 hover:bg-transparent',
                                isAnimating ? 'animate-bounce' : '',
                                multiSelectVariants({ variant })
                            )"
                            :style="{ animationDuration: `${animation}s` }"
                        >
                            + {{ selectedValues.length - maxCount }} more
                            <XCircle
                                class="ml-2 h-4 w-4 cursor-pointer"
                                @click.stop="clearExtraOptions"
                            />
                        </Badge>
                    </div>
                    <div class="flex items-center justify-between">
                        <XIcon
                            class="h-4 mx-2 cursor-pointer text-muted-foreground"
                            @click.stop="handleClear"
                        />
                        <Separator orientation="vertical" class="flex min-h-6 h-full" />
                        <ChevronDown class="h-4 mx-2 cursor-pointer text-muted-foreground" />
                    </div>
                </div>
                <div v-else class="flex items-center justify-between w-full mx-auto">
                    <span class="text-sm text-muted-foreground mx-3">
                        {{ placeholder }}
                    </span>
                    <ChevronDown class="h-4 cursor-pointer text-muted-foreground mx-2" />
                </div>
            </Button>
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
                            key="all"
                            @select="toggleAll"
                            class="cursor-pointer"
                        >
                            <div
                                :class="cn(
                                    'mr-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary',
                                    selectedValues.length === options.length
                                        ? 'bg-primary text-primary-foreground'
                                        : 'opacity-50 [&_svg]:invisible'
                                )"
                            >
                                <CheckIcon class="h-4 w-4" />
                            </div>
                            <span>(Select All)</span>
                        </CommandItem>
                        <CommandItem
                            v-for="option in options"
                            :key="option.value"
                            @select="() => toggleOption(option.value)"
                            class="cursor-pointer"
                        >
                            <div
                                :class="cn(
                                    'mr-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary',
                                    selectedValues.includes(option.value)
                                        ? 'bg-primary text-primary-foreground'
                                        : 'opacity-50 [&_svg]:invisible'
                                )"
                            >
                                <CheckIcon class="h-4 w-4" />
                            </div>
                            <component
                                v-if="option.icon"
                                :is="option.icon"
                                class="mr-2 h-4 w-4 text-muted-foreground"
                            />
                            <span>{{ option.label }}</span>
                        </CommandItem>
                    </CommandGroup>
                    <CommandSeparator />
                    <CommandGroup>
                        <div class="flex items-center justify-between">
                            <template v-if="selectedValues.length > 0">
                                <CommandItem
                                    @select="handleClear"
                                    class="flex-1 justify-center cursor-pointer"
                                >
                                    Clear
                                </CommandItem>
                                <Separator
                                    orientation="vertical"
                                    class="flex min-h-6 h-full"
                                />
                            </template>
                            <CommandItem
                                @select="() => isPopoverOpen = false"
                                class="flex-1 justify-center cursor-pointer max-w-full"
                            >
                                Close
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
</template>

<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue';
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
import { Button } from '@/components/ui/button';
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
}

const props = withDefaults(defineProps<MultiSelectProps>(), {
    modelValue: () => [],
    placeholder: 'Select options',
    animation: 0,
    maxCount: 3,
    modalPopover: false,
    className: '',
    variant: 'default',
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
    (newValue) => {
        selectedValues.value = [...newValue];
    },
    { deep: true }
);

// Emit changes when selectedValues changes
watch(
    selectedValues,
    (newValue) => {
        emit('update:modelValue', newValue);
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

const toggleOption = (option: string) => {
    const newSelectedValues = selectedValues.value.includes(option)
        ? selectedValues.value.filter((value) => value !== option)
        : [...selectedValues.value, option];
    selectedValues.value = newSelectedValues;
};

const handleClear = () => {
    selectedValues.value = [];
};

const handleTogglePopover = () => {
    isPopoverOpen.value = !isPopoverOpen.value;
};

const clearExtraOptions = () => {
    const newSelectedValues = selectedValues.value.slice(0, props.maxCount);
    selectedValues.value = newSelectedValues;
};

const toggleAll = () => {
    if (selectedValues.value.length === props.options.length) {
        handleClear();
    } else {
        const allValues = props.options.map((option) => option.value);
        selectedValues.value = allValues;
    }
};

const getOptionLabel = (value: string) => {
    return props.options.find((o) => o.value === value)?.label || '';
};

const getOptionIcon = (value: string) => {
    return props.options.find((o) => o.value === value)?.icon;
};
</script> 