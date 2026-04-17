import type { Member } from '~/types'

export default eventHandler(async (event) => {
  try {
    const query = getQuery(event)
    const config = useRuntimeConfig()
    const apiBase = config.public.apiBase || 'http://localhost:8000/api'

    // Récupérer le token depuis les headers ou cookies
    const token = getHeader(event, 'authorization')?.replace('Bearer ', '') || 
                  getCookie(event, 'token') ||
                  ''

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    }

    if (token) {
      headers['Authorization'] = `Bearer ${token}`
    }

    // Appel à l'API backend pour récupérer les utilisateurs (membres du personnel)
    // Augmenter per_page pour récupérer tous les utilisateurs
    const response = await $fetch(`${apiBase}/users`, {
      method: 'GET',
      headers,
      params: {
        per_page: query.per_page || 1000, // Augmenté à 1000 pour récupérer tous les membres
        q: query.q || ''
      }
    })

    // Transformer les données du backend en format Member pour le frontend
    const users = (response as any).data || []
    
    // Logger pour debug
    console.log(`[members.ts] Found ${users.length} users from backend`)
    
    const members = users.map((user: any) => {
      // S'assurer que les rôles sont bien un tableau
      const roles = Array.isArray(user.roles) ? user.roles : []
      const primaryRole = roles[0]?.name || 'Client'
      const roleDisplay = primaryRole === 'Super-Admin' ? 'owner' : 
                         primaryRole === 'Client' ? 'member' : 'member'
      
      return {
        id: user.id,
        name: user.name,
        username: user.email?.split('@')[0] || user.name?.toLowerCase().replace(/\s+/g, '') || '',
        role: roleDisplay,
        avatar: {
          src: `https://i.pravatar.cc/128?u=${user.id}`
        },
        email: user.email,
        roles: roles, // S'assurer que c'est toujours un tableau
        createdAt: user.created_at
      } as Member & { id: number, email: string, roles: any[], createdAt: string }
    })
    
    console.log(`[members.ts] Returning ${members.length} members`)
    return members
  } catch (error: any) {
    // Logger les erreurs pour debug
    console.error('[members.ts] Error fetching members:', error)
    console.error('[members.ts] Error details:', {
      statusCode: error.statusCode || error.status,
      message: error.message,
      data: error.data || error.response?.data
    })
    // Retourner un tableau vide en cas d'erreur
    return []
  }
})
