<script setup>
import {computed} from 'vue'
import {videoSource} from '../utils/video.js'
const props=defineProps({url:String,title:{type:String,default:'Vídeo da plataforma'}}),source=computed(()=>videoSource(props.url))
</script><template><div v-if="source" class="ratio ratio-16x9 mb-3"><video v-if="source.type==='video'" :src="source.url" :aria-label="title" controls preload="none"/><iframe v-else :src="source.url" :title="title" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen referrerpolicy="no-referrer"/></div></template>
