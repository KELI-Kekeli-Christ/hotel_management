import type { Mail } from '~/types'

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

    // Récupérer les notifications depuis l'API backend
    const response = await $fetch(`${apiBase}/notifications`, {
      method: 'GET',
      headers,
      params: {
        per_page: query.per_page || 100,
        unread_only: query.unread_only || false
      }
    })

    // Transformer les notifications en format Mail pour l'inbox
    const notifications = (response as any).data || []
    
    // Récupérer aussi les commandes pour les inclure dans l'inbox (seulement pour les admins)
    let orders: any[] = []
    try {
      const ordersResponse = await $fetch(`${apiBase}/orders`, {
        method: 'GET',
        headers,
        params: {
          per_page: 50,
          status: 'pending'
        }
      })
      orders = (ordersResponse as any).data || []
      
      // Charger les relations user si elles ne sont pas déjà chargées
      for (const order of orders) {
        if (!order.user && order.user_id) {
          try {
            const userResponse = await $fetch(`${apiBase}/users/${order.user_id}`, {
              method: 'GET',
              headers
            })
            order.user = (userResponse as any)
          } catch (e) {
            // Ignorer les erreurs
          }
        }
      }
    } catch (error: any) {
      // Ignorer les erreurs si l'utilisateur n'a pas les permissions (401/403)
      if (error.statusCode !== 401 && error.statusCode !== 403 && error.status !== 401 && error.status !== 403) {
        console.log('Could not fetch orders for inbox:', error)
      }
    }

    // Transformer les notifications en format Mail
    const mails = notifications.map((notification: any, index: number) => {
      const notificationDate = notification.created_at || notification.updated_at || new Date().toISOString()
      
      return {
        id: notification.id || index,
        unread: !notification.read,
        from: {
          id: notification.user?.id || notification.data?.user_id || 1,
          name: notification.data?.user_name || 'Système',
          email: notification.user?.email || 'system@hotel.com',
          avatar: {
            src: `https://i.pravatar.cc/128?u=${notification.user?.id || notification.data?.user_id || 1}`
          }
        },
        subject: notification.title || 'Notification',
        body: notification.message || '',
        date: notificationDate,
        type: notification.type || 'notification',
        data: notification.data || {}
      } as Mail
    })

    // Ajouter les commandes en attente comme messages dans l'inbox
    const orderMails = orders.map((order: any, index: number) => {
      const orderDate = order.created_at || new Date().toISOString()
      const orderType = order.order_type === 'room_service' ? 'Service en chambre' : 'Restaurant'
      const location = order.location || `Chambre ${order.room_number || 'N/A'}`
      
      return {
        id: `order-${order.id}`,
        unread: order.status === 'pending',
        from: {
          id: order.user?.id || order.user_id || 1,
          name: order.user?.name || 'Client',
          email: order.user?.email || 'client@example.com',
          avatar: {
            src: `https://i.pravatar.cc/128?u=${order.user?.id || order.user_id || 1}`
          }
        },
        subject: `${orderType} - ${location}`,
        body: `Commande #${order.id} pour un montant de ${order.total} XOF${order.note ? `\n\nNote: ${order.note}` : ''}`,
        date: orderDate,
        type: 'order',
        data: {
          order_id: order.id,
          order_type: order.order_type,
          location: order.location,
          total: order.total,
          status: order.status
        }
      } as Mail
    })

    // Combiner notifications et commandes, trier par date
    const allMails = [...mails, ...orderMails].sort((a, b) => {
      return new Date(b.date).getTime() - new Date(a.date).getTime()
    })

    return allMails
  } catch (error: any) {
    // Ne pas logger les erreurs 401 (non authentifié) car c'est normal côté serveur
    if (error.statusCode !== 401 && error.status !== 401) {
      console.error('Error fetching mails:', error)
    }
    // Retourner un tableau vide en cas d'erreur
    return []
  }
})
