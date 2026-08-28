import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[5px] text-sm font-medium transition-colors duration-300 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "btn-swipe bg-primary text-primary-foreground shadow-xs hover:text-cream [--swipe-bg:var(--ink)]",
        destructive:
          "btn-swipe bg-destructive text-white shadow-xs focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 [--swipe-bg:var(--ink)]",
        outline:
          "btn-swipe border border-input bg-background shadow-xs hover:border-terracotta hover:text-cream [--swipe-bg:var(--terracotta)]",
        secondary:
          "btn-swipe bg-secondary text-secondary-foreground shadow-xs hover:text-cream [--swipe-bg:var(--terracotta)]",
        ghost:
          "btn-swipe hover:text-accent-foreground [--swipe-bg:var(--accent)]",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-9 px-4 py-2 has-[>svg]:px-3",
        sm: "h-8 px-3 has-[>svg]:px-2.5",
        lg: "h-10 px-6 has-[>svg]:px-4",
        icon: "size-9",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }), "cursor-pointer")}
      {...props}
    />
  )
}

export { Button, buttonVariants }
