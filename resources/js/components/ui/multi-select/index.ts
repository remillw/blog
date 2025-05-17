import { defineComponent, h } from 'vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../select'

interface Option {
  value: string | number
  label: string
}

export const MultiSelect = defineComponent({
  name: 'MultiSelect',
  props: {
    modelValue: {
      type: Array as () => (string | number)[],
      default: () => []
    },
    options: {
      type: Array as () => Option[],
      required: true
    },
    placeholder: {
      type: String,
      default: 'Select items...'
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const handleSelect = (value: string | number) => {
      const newValue = props.modelValue.includes(value)
        ? props.modelValue.filter(v => v !== value)
        : [...props.modelValue, value]
      emit('update:modelValue', newValue)
    }

    return () => h(Select, {
      value: props.modelValue,
      onUpdate: (value) => emit('update:modelValue', value),
      disabled: props.disabled
    }, {
      trigger: () => h(SelectTrigger, {
        class: 'w-full'
      }, {
        default: () => h(SelectValue, {
          placeholder: props.placeholder
        })
      }),
      content: () => h(SelectContent, {}, {
        default: () => props.options.map((option) => h(SelectItem, {
          value: option.value,
          onClick: () => handleSelect(option.value)
        }, {
          default: () => option.label
        }))
      })
    })
  }
}) 