<script setup lang="ts">
import type { NavigationMenuItem } from "@nuxt/ui";
import { useAuthStore } from "~/stores/auth";

const route = useRoute();
const toast = useToast();
const router = useRouter();
const authStore = useAuthStore();

const open = ref(false);

// Navigation pour les clients
const clientLinks: NavigationMenuItem[][] = [
  [
    {
      label: "Accueil",
      icon: "i-lucide-house",
      to: "/client",
      onSelect: () => {
        open.value = false;
      },
    },
    {
      label: "Chambres",
      icon: "i-lucide-bed-double",
      to: "/client/rooms",
      onSelect: () => {
        open.value = false;
      },
    },
    {
      label: "Mes Réservations",
      icon: "i-lucide-calendar-check",
      to: "/client/bookings",
      onSelect: () => {
        open.value = false;
      },
    },
    {
      label: "Services",
      icon: "i-lucide-concierge-bell",
      to: "/client/services",
      onSelect: () => {
        open.value = false;
      },
    },
    {
      label: "Mes Commandes",
      icon: "i-lucide-shopping-cart",
      to: "/client/orders",
      onSelect: () => {
        open.value = false;
      },
    },
  ],
  [
    {
      label: "Help & Support",
      icon: "i-lucide-info",
      to: "#",
    },
  ],
];

// Navigation pour les admins/staff
const adminLinks: NavigationMenuItem[][] = [
  [
    {
      label: "Dashboard",
      icon: "i-lucide-house",
      to: "/dashboard",
      onSelect: () => {
        open.value = false;
      },
    },
    {
      label: "Rooms",
      icon: "i-lucide-bed-double",
      to: "/admin/chambres",
      onSelect: () => {
        console.log("Rooms clicked -> route:", route.fullPath);
        open.value = false;
        router.push("/admin/chambres").catch(() => {});
      },
    },
    {
      label: "Réservations",
      icon: "i-lucide-calendar-check",
      to: "/admin/reservations",
    },
    {
      label: "Services",
      icon: "i-lucide-concierge-bell",
      to: "/admin/services",
    },
    {
      label: "Utilisateurs",
      icon: "i-lucide-users",
      to: "/admin/users",
    },
    {
      label: "Inbox",
      icon: "i-lucide-inbox",
      to: "/inbox",
      badge: "4",
      onSelect: () => {
        open.value = false;
      },
    },
    {
      label: "Customers",
      icon: "i-lucide-users",
      to: "/customers",
      onSelect: () => {
        open.value = false;
      },
    },
    {
      label: "Settings",
      to: "/settings",
      icon: "i-lucide-settings",
      defaultOpen: true,
      type: "trigger",
      children: [
        {
          label: "General",
          to: "/settings",
          exact: true,
          onSelect: () => {
            open.value = false;
          },
        },
        {
          label: "Members",
          to: "/settings/members",
          onSelect: () => {
            open.value = false;
          },
        },
        {
          label: "Notifications",
          to: "/settings/notifications",
          onSelect: () => {
            open.value = false;
          },
        },
        {
          label: "Security",
          to: "/settings/security",
          onSelect: () => {
            open.value = false;
          },
        },
        {
          label: "Configuration Hôtel",
          to: "/admin/hotel-config",
          icon: "i-lucide-building",
          onSelect: () => {
            open.value = false;
          },
        },
      ],
    },
  ],
  [
    {
      label: "Feedback",
      icon: "i-lucide-message-circle",
      to: "https://github.com/nuxt-ui-templates/dashboard",
      target: "_blank",
    },
    {
      label: "Help & Support",
      icon: "i-lucide-info",
      to: "https://github.com/nuxt-ui-templates/dashboard",
      target: "_blank",
    },
  ],
];

// Déterminer les liens à afficher selon le rôle
const links = computed(() => {
  if (authStore.hasRole("Client")) {
    return clientLinks;
  }
  return adminLinks;
});

const groups = computed(() => [
  {
    id: "links",
    label: "Go to",
    items: links.value.flat(),
  },
  {
    id: "code",
    label: "Code",
    items: [
      {
        id: "source",
        label: "View page source",
        icon: "i-simple-icons-github",
        to: `https://github.com/nuxt-ui-templates/dashboard/blob/main/app/pages${
          route.path === "/" ? "/index" : route.path
        }.vue`,
        target: "_blank",
      },
    ],
  },
]);

onMounted(async () => {
  const cookie = useCookie("cookie-consent");
  if (cookie.value === "accepted") {
    return;
  }

  toast.add({
    title:
      "We use first-party cookies to enhance your experience on our website.",
    duration: 0,
    close: false,
    actions: [
      {
        label: "Accept",
        color: "neutral",
        variant: "outline",
        onClick: () => {
          cookie.value = "accepted";
        },
      },
      {
        label: "Opt out",
        color: "neutral",
        variant: "ghost",
      },
    ],
  });
});
</script>

<template>
  <UDashboardGroup unit="rem">
    <UDashboardSidebar
      id="default"
      v-model:open="open"
      collapsible
      resizable
      class="bg-elevated/25"
      :ui="{ footer: 'lg:border-t lg:border-default' }"
    >
      <template #header="{ collapsed }">
        <TeamsMenu :collapsed="collapsed" />
      </template>

      <template #default="{ collapsed }">
        <UDashboardSearchButton
          :collapsed="collapsed"
          class="bg-transparent ring-default"
        />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="links[0]"
          orientation="vertical"
          tooltip
          popover
        />

        <UNavigationMenu
          :collapsed="collapsed"
          :items="links[1]"
          orientation="vertical"
          tooltip
          class="mt-auto"
        />
      </template>

      <template #footer="{ collapsed }">
        <UserMenu :collapsed="collapsed" />
      </template>
    </UDashboardSidebar>

    <UDashboardSearch :groups="groups" />

    <NuxtPage />

    <NotificationsSlideover />
  </UDashboardGroup>
</template>
