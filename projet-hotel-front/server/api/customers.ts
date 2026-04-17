import type { User } from '~/types'

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

    // Appel à l'API backend pour récupérer les utilisateurs
    const response = await $fetch(`${apiBase}/users`, {
      method: 'GET',
      headers,
      params: {
        per_page: query.per_page || 100,
        q: query.q || ''
      }
    })

    // Transformer les données du backend en format attendu par le frontend
    const users = (response as any).data || []
    
    return users.map((user: any) => {
      // Déterminer le statut basé sur le rôle
      let status = 'subscribed'
      if (user.roles && user.roles.length > 0) {
        const roleNames = user.roles.map((r: any) => r.name?.toLowerCase() || '')
        if (roleNames.includes('client')) {
          status = 'subscribed'
        } else if (roleNames.includes('super-admin') || roleNames.includes('receptionist')) {
          status = 'subscribed'
        } else {
          status = 'unsubscribed'
        }
      }

      // Obtenir le rôle principal pour l'affichage
      const primaryRole = user.roles?.[0]?.name || 'Client'
      
      return {
        id: user.id,
        name: user.name,
        email: user.email,
        avatar: {
          src: `https://i.pravatar.cc/128?u=${user.id}`
        },
        status,
        location: primaryRole, // Afficher le rôle comme location
        roles: user.roles || [],
        createdAt: user.created_at,
        updatedAt: user.updated_at
      }
    })
  } catch (error: any) {
    // Ne pas logger les erreurs 401 (non authentifié) car c'est normal côté serveur
    if (error.statusCode !== 401 && error.status !== 401) {
      console.error('Error fetching customers:', error)
    }
    // Retourner un tableau vide en cas d'erreur
    return []
  }
})
