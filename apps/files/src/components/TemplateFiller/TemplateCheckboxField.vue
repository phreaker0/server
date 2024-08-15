<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="template-field__checkbox">
		<label :for="fieldId">
			{{ fieldLabel }}
		</label>

		<NcCheckboxRadioSwitch :id="fieldId"
			:checked.sync="value"
			type="switch"
			@update:checked="$emit('input', [field.index, 'checked', value])" />
	</div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default defineComponent({
	name: 'TemplateCheckboxField',

	components: {
		NcCheckboxRadioSwitch,
	},

	props: {
		field: {
			type: Object,
			default: () => {},
		},
	},

	data() {
		return {
			value: this.field.checked ?? false,
		}
	},

	computed: {
		fieldLabel() {
			const label = this.field.name ?? this.field.alias ?? 'Unknown field'

			return label.charAt(0).toUpperCase() + label.slice(1)
		},
		fieldId() {
			return 'checkbox-field' + this.field.index
		},
	},
})
</script>

<style lang="scss" scoped>
.template-field__checkbox {
  margin: 20px 0;
  display: flex;
  align-items: center;

  label {
    font-weight: bold;
    flex-grow: 1;
  }
}
</style>
