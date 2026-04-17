<script setup lang="ts">
definePageMeta({
  layout: false,
})

onMounted(() => {
  const authStore = useAuthStore()
  
  if (authStore.isAuthenticated) {
    if (authStore.hasRole("Client")) {
      router.push("/client")
    } else {
      router.push("/dashboard")
    }
  } else {
    router.push("/home")
  }
})

const router = useRouter()
</script>

<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="text-center">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mb-4"></div>
      <p class="text-gray-600 dark:text-gray-400">Redirection en cours...</p>
    </div>
  </div>
</template>

<style scoped>
/* Empty style section to prevent PostCSS parsing errors */
</style>
