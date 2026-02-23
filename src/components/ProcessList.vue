<template>
    <div class="signd-process-list">
        <ProcessStatus
            v-for="process in processes"
            :key="process.processId"
            :process="process"
            @download="$emit('download', process.processId)"
            @resume-wizard="$emit('resume-wizard', process.processId)"
            @cancel-wizard="$emit('cancel-wizard', process.processId)" />
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import ProcessStatus from './ProcessStatus.vue'
import type { SigndProcess } from '../services/api'

export default defineComponent({
    name: 'ProcessList',

    components: {
        ProcessStatus,
    },

    props: {
        processes: {
            type: Array as PropType<SigndProcess[]>,
            required: true,
        },
    },

    emits: ['download', 'resume-wizard', 'cancel-wizard'],
})
</script>

<style lang="scss" scoped>
.signd-process-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
</style>
