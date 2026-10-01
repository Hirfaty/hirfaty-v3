import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "w-full bg-transparent border-0 border-b focus:border-ink focus:outline-none py-3 pe-8 text-ink placeholder:text-ink-soft/30 font-sans text-base",
        className
      )}
      {...props}
    />
  )
}

export { Input }
